<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

use IvyPaymentPlugin\Components\BasketPersisterTrait;
use IvyPaymentPlugin\Exception\IvyException;
use IvyPaymentPlugin\Logger\IvyPaymentLogger;
use IvyPaymentPlugin\Models\IvyTransaction;
use IvyPaymentPlugin\Service\ExpressService;
use IvyPaymentPlugin\Service\IvyPaymentHelper;
use IvyPaymentPlugin\Service\StoreProxy;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Components\Model\ModelManager;
use IvyPaymentPlugin\Components\IvyJsonResponse;
use Symfony\Component\HttpFoundation\Cookie;


class Shopware_Controllers_Frontend_IvyExpress extends Shopware_Controllers_Frontend_Checkout implements CSRFWhitelistAware
{

    /**
     * @var ModelManager
     */
    private $em;

    /**
     * @var IvyPaymentLogger
     */
    private $logger;

    /**
     * @var ExpressService
     */
    private $expressService;

    /**
     * @var IvyPaymentHelper|mixed|object|\Symfony\Component\DependencyInjection\Container|null
     */
    private $ivyHelper;


    public function getWhitelistedCSRFActions()
    {
        return ['start', 'callback', 'confirm', 'finish', 'proxy'];
    }

    use BasketPersisterTrait;

    /**
     * @return void
     * @throws Exception
     */
    public function preDispatch()
    {
        parent::preDispatch();
        $this->em = $this->getModelManager();
        $this->expressService = $this->container->get('ivy_express_service');
        $this->ivyHelper = $this->container->get('ivy_payment_helper');
        /** @var IvyPaymentHelper $ivyHelper */
        $ivyHelper = $this->expressService->getIvyHelper();
        /** @var StoreProxy $storeProxy */
        $storeProxy = $this->container->get('ivy_store_proxy');
        $this->logger = $this->expressService->getLogger();
        if ($this->request->getActionName() !== 'start' && $this->request->getActionName() !== 'refresh' ) {
            if ($this->session->offsetExists('IvyNotExpressCheckout') ) {
                $this->logger = $this->logger->withName('normal');
            }
            $referenceId = $this->request->get('reference');
            if ($referenceId === null) {
                $payload = \json_decode($this->request->getContent(), true);
                if (\is_array($payload) && isset($payload['referenceId'])) {
                    $referenceId = (string)$payload['referenceId'];
                }
            }
            $this->logger->info('callback reference: ' . $referenceId);
            /** @var IvyTransaction|null $ivyPaymentSession */
            $ivyPaymentSession = $this->em->getRepository(IvyTransaction::class)->findOneBy(
                ['reference' => $referenceId]
            );
            if (!$ivyPaymentSession instanceof IvyTransaction) {
                $this->logger->error('transaction not found by reference');
                $this->response = new IvyJsonResponse([]);
                $this->response->send();
                $this->get('kernel')->terminate($this->request, $this->response);
                exit(0);
            }
            $swContextToken = $ivyPaymentSession->getSwContextToken();
            $this->logger->debug('send proxy request');
            try {
                $proxyResponse = $storeProxy->proxy($this->request, $swContextToken);
            } catch (\Throwable $e) {
                $this->logger->error('proxy request error');
                $this->logger->error($e->getMessage());
                $this->logger->error($e->getTraceAsString());
            }
            $this->logger->debug('received proxy response');
            $content = (string)$proxyResponse->getBody();
            $this->logger->debug($content);
            $this->response->setContent($content);
            $this->response->headers->set('Content-Type', $proxyResponse->getHeader('Content-Type'));
            $signature = $ivyHelper->sign($content);
            $this->response->headers->set('X-Ivy-Signature', $signature);
            $this->logger->info('output body:' . $content);
            $this->logger->info('X-Ivy-Signature:' . $signature);
            $this->response->send();
            $this->get('kernel')->terminate($this->request, $this->response);
            exit(0);
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    public function startAction()
    {
        $data = [];
        try {
            $isExpress = $this->Request()->get('express', true);
            $isExpress = $isExpress === 'true' || $isExpress === true;
            $basket = $this->getBasket();
            $country = $this->getSelectedCountry();
            $dispatch = $this->getSelectedDispatch();
            if (!\is_array($dispatch)) {
                $dispatch = [];
            }
            if ($isExpress) {
                $this->session->offsetUnset('IvyNotExpressCheckout');
                $this->logger->info('-- create new express ivy session');
                $ivySession = $this->expressService->createExpressSession($basket, $dispatch, $country);
            } else {
                $this->session->offsetSet('IvyNotExpressCheckout', true);
                $this->logger->info('-- create new ivy session');
                $order = $this->ivyHelper->getCurrentTemporaryOrder();
                if ($order) {
                    $swPaymentToken = $this->persistBasket();
                    $ivySession = $this->ivyHelper->createIvySession($order, $swPaymentToken);
                } else {
                    throw new IvyException('temporary order not found');
                }
            }

            $swContexToken = $this->expressService->generateSwContextToken();

            if (empty($ivySession['redirectUrl'])) {
                throw new IvyException('Ivy session has not redirectUrl');
            }
            $redirectUrl = $ivySession['redirectUrl'];

            $referenceId = (string)(isset($ivySession['referenceId']) ? $ivySession['referenceId'] : null);
            $ivySessionId = (string)(isset($ivySession['id']) ? $ivySession['id'] : null);

            if ($referenceId === '') {
                throw new IvyException('Ivy session has not referenceId');
            }
            if ($ivySessionId === '') {
                throw new IvyException('Ivy session has not session id');
            }

            $shopwareSessionId = $this->session->get('sessionId');
            $openedTransaction = $this->em->getRepository(IvyTransaction::class)->findOneBy([
                'initialSessionId' => $shopwareSessionId,
                'orderId' => null,
                'status' => IvyTransaction::STATUS_CREATED
            ]);
            if ($openedTransaction instanceof IvyTransaction) {
                $this->logger->debug('updated current transaction');
                $transaction = $openedTransaction;
            } else {
                $this->logger->debug('create new transaction');
                $transaction = new IvyTransaction();
                $this->em->persist($transaction);
                $transaction->setInitialSessionId($shopwareSessionId);
                $transaction->setCreated(new \DateTime((string)$ivySession['createdAt']));
            }
            $transaction->setUpdated(new \DateTime());
            $transaction->setAmount($basket['sAmount']);
            $transaction->setAppId($ivySession['appId']);
            $transaction->setIvySessionId($ivySession['id']);
            $transaction->setUpdated(new \DateTime((string)$ivySession['updatedAt']));
            $transaction->setStatus(IvyTransaction::STATUS_CREATED);
            $transaction->setReference($referenceId);
            $transaction->setSwContextToken($swContexToken);
            $transaction->setExpress($isExpress);
            if (!$isExpress && isset($swPaymentToken)) {
                $transaction->setSwPaymentToken($swPaymentToken);
            }
            $this->logger->debug('flush transaction');
            $this->em->flush($transaction);
            $this->logger->info('redirect to ' . $redirectUrl);
            $data['success'] = true;
            $data['redirectUrl'] = $redirectUrl;
            $data['referenceId'] = $referenceId;
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->logger->error($message);
            $data['success'] = false;
            $data['error'] = $message;
        }
        \ini_set('serialize_precision', '3');
        $this->response = new IvyJsonResponse($data);
        $this->response->send();
        $this->get('kernel')->terminate($this->request, $this->response);
        exit(0);
    }

    public function refreshAction()
    {
        $ivyPaymentSession = null;
        $data = [];
        $referenceId = $this->request->get('reference');
        if (!empty($referenceId)) {
            $ivyPaymentSession = $this->em->getRepository(IvyTransaction::class)->findOneBy(
                ['reference' => $referenceId]
            );
        }
        if ($ivyPaymentSession instanceof  IvyTransaction) {
            $data['foundByReference'] = true;
        } else {
            $ivyPaymentSession = $this->em->getRepository(IvyTransaction::class)->findOneBy(
                ['initialSessionId' => Shopware()->Session()->getId()]
            );
        }

        $this->response = new IvyJsonResponse($data);
        if ($ivyPaymentSession instanceof IvyTransaction) {
            if (!isset($data['foundByReference'])) {
                $data['foundByCurrentSession'] = true;
            }
            $swContextToken = $ivyPaymentSession->getSwContextToken();
            $cookies = \json_decode(\base64_decode($swContextToken), true);
            $basePath = Shopware()->Shop()->getBasePath();
            foreach ($cookies as $name => $value) {
                $cookie = new Cookie($name, $value, 0, $basePath, '', '');
                $this->response->headers->setCookie($cookie);
                if (\preg_match('/^session-\d/', $name)) {
                    $data[$name] = $value;
                }
            }
        } else {
            $this->logger->error('can not sync shopware session with express');
        }
        $this->response->setData($data);
        $this->response->send();
        $this->get('kernel')->terminate($this->request, $this->response);
        exit(0);
    }
}
