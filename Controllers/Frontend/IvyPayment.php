<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use IvyPaymentPlugin\Exception\IvyException;
use IvyPaymentPlugin\Models\IvyTransaction;
use IvyPaymentPlugin\Service\IvyPaymentHelper;
use Monolog\Logger;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payment controller
 */
class Shopware_Controllers_Frontend_IvyPayment extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    /**
     * @var ModelManager
     */
    private $em;

    /**
     * @var Enlight_Controller_Router
     */
    private $router;

    /**
     * @var IvyPaymentHelper
     */
    private $ivyHelper;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @return string[]
     */
    public function getWhitelistedCSRFActions()
    {
        return ['notify'];
    }

    /**
     * @return void
     * @throws Exception
     */
    public function preDispatch()
    {
        parent::preDispatch();
        $this->em = $this->getModelManager();
        $this->router = $this->Front()->Router();
        $this->ivyHelper = $this->container->get('ivy_payment_helper');
        $this->logger = $this->ivyHelper->getLogger();
    }

    /**
     * @return void
     * @throws IvyException
     * @throws Exception
     */
    public function indexAction()
    {
        if ('ivy_payment' !== $this->getPaymentShortName()) {
            $this->redirect(['controller' => 'checkout']);
        }

        $swPaymentToken = $this->persistBasket();
        $amount = $this->getAmount();
        $order = $this->ivyHelper->getCurrentTemporaryOrder();
        $redirectUrl = '';
        try {
            $ivySession = $this->ivyHelper->createIvySession($order, $swPaymentToken);
            if (empty($ivySession['redirectUrl'])) {
                throw new IvyException('Ivy session has not redirectUrl');
            }

            $referenceId = (string)(isset($ivySession['referenceId']) ? $ivySession['referenceId'] : null);
            $ivySessionId = (string)(isset($ivySession['id']) ? $ivySession['id'] : null);

            if ($referenceId === '') {
                throw new IvyException('Ivy session has not referenceId');
            }
            if ($ivySessionId === '') {
                throw new IvyException('Ivy session has not session id');
            }

            if ($order !== null) {
                $existedTransaction = $this->em->getRepository(IvyTransaction::class)
                    ->findOneBy([
                        'orderId' => $order->getId(),
                        'reference' => $referenceId,
                    ]);
            } else {
                throw new IvyException('can not load temporary order');
            }

            $redirectUrl = $ivySession['redirectUrl'];
            if ($existedTransaction) {
                $this->logger->debug('use existed transaction');
                $transaction = $existedTransaction;
            } else {
                $this->logger->debug('create new transaction');
                $transaction = new IvyTransaction();
                $this->em->persist($transaction);
            }

            $transaction->setUpdated(new \DateTime());
            $transaction->setAmount($amount);
            $transaction->setAppId($ivySessionId);
            $transaction->setIvySessionId($ivySession['id']);
            $transaction->setSwPaymentToken($swPaymentToken);
            $transaction->setCreated(new \DateTime((string)$ivySession['createdAt']));
            $transaction->setUpdated(new \DateTime((string)$ivySession['updatedAt']));
            $transaction->setStatus(IvyTransaction::STATUS_CREATED);
            $transaction->setReference($referenceId);
            $this->logger->debug('flush transaction 1');
            $this->em->flush($transaction);

            $orderNumber = (string)$this->saveOrder(
                $ivySessionId,
                $referenceId,
                Status::PAYMENT_STATE_OPEN
            );

            if ($orderNumber === '') {
                throw new IvyException('can not save order');
            }

            $this->logger->info('created order number ' . $orderNumber);
            $createdOrder = $this->em
                ->getRepository(Order::class)
                ->findOneBy([ 'number' => $orderNumber]);
            $this->logger->debug('created order id ' . $createdOrder->getId());

            $transaction->setUpdated(new \DateTime());
            $transaction->setOrder($createdOrder);
            $this->logger->debug('flush transaction 2');
            $this->em->flush($transaction);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());
            $this->redirect(['controller' => 'IvyPayment', 'action' => 'error']);
        }
        $this->redirect($redirectUrl);
    }

    /**
     * @return void
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    public function successAction()
    {
        $request = $this->Request();
        if (\method_exists($request,'getQueryString')) {
            $this->logger->info('received success response: ' . $request->getQueryString());
        } else {
            $this->logger->info('received success response: ' . \print_r($request->getQuery(), true));
        }

        $signature = $request->get('_sw_payment_token');

        try {
            $basket = $this->loadBasketFromSignature($signature);
            $this->verifyBasketSignature($signature, $basket);
        } catch (\Exception $e) {
            $this->logger->error('successAction verify error');
            $this->redirect(['controller' => 'IvyPayment', 'action' => 'error']);
        }

        $transaction = Shopware()->Models()
            ->getRepository(IvyTransaction::class)
            ->findOneBy(['swPaymentToken' => $signature]);

        $status = IvyTransaction::STATUS_AUTH;
        $transaction->setStatus($status);
        $transaction->setUpdated(new \DateTime());
        $transaction->setIvyOrderId($request->get('order-id'));
        $this->em->flush($transaction);
        $sOrder = Shopware()->Modules()->Order();
        $paymentStatus = IvyTransaction::STATUS_MAP[$status] ?: null;
        if ($paymentStatus) {
            $sOrder->setPaymentStatus($transaction->getOrderId(), $paymentStatus);
        }

        $this->ivyHelper->updateOrder($transaction);
        $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
    }

    /**
     * @return void
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws IvyException
     */
    public function errorAction()
    {
        $orderId = $this->ivyHelper->getCurrentTemporaryOrder()->getId();
        $transaction = $this->em
            ->getRepository(IvyTransaction::class)
            ->findOneBy(['orderId' => $orderId]);
        if ($transaction) {
            $transaction->setStatus(IvyTransaction::STATUS_FAILED);
            $transaction->setUpdated(new \DateTime());
            $this->em->flush($transaction);
        }
        $this->logger->error('payment error, redirect to checkout');
        $this->redirect(['controller' => 'checkout', 'action' => 'confirm']);
    }

    /**
     * @return void
     */
    public function notifyAction()
    {
        $response = $this->handleWebhook();
        $response->send();
        exit(0);
    }

    /**
     * @return JsonResponse
     */
    private function handleWebhook()
    {
        $request = $this->Request();
        $body = $request->getRawBody();
        $this->logger->debug('receive notification ' . \print_r($body, true));
        $jsonBody = \json_decode($body, true);
        try {
            $XIvySignature = (string)$request->getHeader('X-Ivy-Signature');
            $this->logger->debug('X-Ivy-Signature: ' . $XIvySignature);
            if (!$this->ivyHelper->validateNotification($XIvySignature, $body)) {
                $this->logger->error('invalid notification signature');
                return new JsonResponse(['success' => false, 'error' => 'invalid notification signature'], Response::HTTP_FORBIDDEN);
            }

            $type = $jsonBody['type'];
            if ($type !== 'order_created' && $type !== 'order_updated') {
                $this->logger->debug('skip notification type ' . $type);
                return new JsonResponse(['success' => false, 'error' => 'skip notification type ' . $type], Response::HTTP_BAD_REQUEST);
            }

            $payload = $jsonBody['payload'];

            if (empty($payload)) {
                $this->logger->error('empty notification payload ');
                return new JsonResponse(['success' => false, 'error' => 'empty notification payload'], Response::HTTP_BAD_REQUEST);
            }

            $paymentToken = isset($payload['metadata']['_sw_payment_token']) ? $payload['metadata']['_sw_payment_token'] : null;
            if (\is_null($paymentToken)) {
                throw new IvyException('missing _sw_payment_token');
            }

            $status = $payload['status'];
            $appId = $payload['appId'];
            $referenceId = $payload['referenceId'];
            $transaction = $this->em->getRepository(IvyTransaction::class)
                ->findOneBy(['reference' => $referenceId]);
            if ($transaction) {
                $transaction->setStatus($status);
                $transaction->setUpdated(new \DateTime());
                $this->em->flush($transaction);
                if (!$transaction->getOrder() || !$transaction->getOrder()->getNumber()) {
                    //skip order ist not yet created
                    return new JsonResponse(['success' => false, 'error' => 'order ist not yet created'], Response::HTTP_BAD_REQUEST);
                }
                $sOrder = Shopware()->Modules()->Order();
                $paymentStatus = IvyTransaction::STATUS_MAP[$status] ?: null;
                if ($paymentStatus) {
                    $sOrder->setPaymentStatus($transaction->getOrderId(), $paymentStatus);
                }
                if ($type === 'order_created') {
                    $this->ivyHelper->updateOrder($transaction);
                }
                return new JsonResponse(['success' => true], Response::HTTP_OK);
            }
            $this->logger->error('ivy transaction by referenceId not found ' . $referenceId);
        } catch (\Exception $e) {
            $this->logger->error('transaction update error: ' . $e->getMessage());
            $this->logger->error($e->getTraceAsString());
            return new JsonResponse(['success' => false, 'error' => 'transaction update error: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new JsonResponse(['success' => false, 'error' => 'unknown error'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
