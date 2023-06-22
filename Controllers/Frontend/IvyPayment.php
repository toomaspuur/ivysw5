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
use IvyPaymentPlugin\Components\IvyJsonResponse;
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
     * @var mixed|object|\Symfony\Component\DependencyInjection\Container|null
     */
    private $expressService;

    /**
     * @return string[]
     */
    public function getWhitelistedCSRFActions()
    {
        return ['notify', 'express', 'createOrder'];
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
        $this->expressService = $this->container->get('ivy_express_service');
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
                $swContexToken = $this->expressService->generateSwContextToken();
                $transaction->setSwContextToken($swContexToken);
            }

            $transaction->setUpdated(new \DateTime());
            $transaction->setAmount($amount);
            $transaction->setAppId($ivySession['appId']);
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
     * @throws Exception
     */
    public function expressAction()
    {
        $this->logger = $this->expressService->getLogger();
        $this->logger->info('express checkout confirm');
        $request = $this->Request();
        $outputData = [];
        $error = null;
        try {
            if ('ivy_payment' !== $this->getPaymentShortName()) {
                throw new IvyException('invalid payment method selected ' . $this->getPaymentShortName());
            }
            $signature = $request->get('_sw_payment_token');
            $basket = $this->loadBasketFromSignature($signature);
            $this->verifyBasketSignature($signature, $basket);

            $referenceId = $this->request->get('referenceId');

            /** @var IvyTransaction $ivyPaymentSession */
            $ivyPaymentSession = $this->em
                ->getRepository(IvyTransaction::class)->findByReference($referenceId);
            if ($ivyPaymentSession === null) {
                throw new IvyException('ivy transaction by reference ' . $referenceId . ' not found');
            }

            $orderNumber = '';
            if ($ivyPaymentSession->getOrderId()) {
                try {
                    $existedOrder = $ivyPaymentSession->getOrder();
                } catch (\Exception $e) {
                    $existedOrder = null;
                }
                if ($existedOrder instanceof Order) {
                    $orderNumber = (string)$existedOrder->getNumber();
                }
            }


            if (!empty($orderNumber)) {
                $this->logger->info('order existing: ' . $orderNumber);
            }

            $outputData = [
                'redirectUrl' => $this->router->assemble(['controller' => 'checkout', 'action' => 'finish']),
                'displayId' => $orderNumber,
                'referenceId' => $orderNumber,
                'metadata' => [
                    '_sw_payment_token' => $signature,
                    'shopwareOrderId' => $orderNumber
                ]
            ];

        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());
            $error = $e->getMessage();
        }

        if ($error) {
            $outputData['error'] = $error;
        }

        $this->logger->info('send proxy response: ' . \print_r($outputData, true));

        \ini_set('serialize_precision', '-1');
        $response = new IvyJsonResponse($outputData);
        $response->send();
        $this->get('kernel')->terminate($this->request, $response);
        exit(0);
    }

    /**
     * @return array|mixed
     */
    private function getPayload()
    {
        $payload = $this->request->request->all();
        if (empty($payload) && !empty((string)$this->request->getContent())) {
            $payload = \json_decode((string)$this->request->getContent(), true);
        }
        $this->logger->debug('payload: ' . \print_r($payload, true));
        return $payload;
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
            $this->logger->error($e->getMessage());
            $this->redirect(['controller' => 'IvyPayment', 'action' => 'error']);
            return;
        }

        $transaction = Shopware()->Models()
            ->getRepository(IvyTransaction::class)
            ->findOneBy(['swPaymentToken' => $signature]);

        if (!$transaction instanceof IvyTransaction) {
            $this->logger->error('transaction with _sw_payment_token ' . $signature . ' not found');
            $this->redirect(['controller' => 'IvyPayment', 'action' => 'error']);
            return;
        }
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

        $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
    }

    /**
     * @return void
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws IvyException|Exception
     */
    public function errorAction()
    {
        try {
            $orderId = $this->ivyHelper->getCurrentTemporaryOrder()->getId();
            $transaction = $this->em
                ->getRepository(IvyTransaction::class)
                ->findOneBy(['orderId' => $orderId]);
            if ($transaction) {
                $transaction->setStatus(IvyTransaction::STATUS_FAILED);
                $transaction->setUpdated(new \DateTime());
                $this->em->flush($transaction);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
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
    private function handleWebhook(): JsonResponse
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
            $this->logger->info('WebHook status is ' . $status);
            $statusForCreateOrder = \in_array($status, IvyTransaction::CREATE_ORDER_STATUSES, true);
            $this->logger->info('status for createOrder ' . \var_export($statusForCreateOrder, true));
            $referenceId = $payload['referenceId'];
            $transaction = $this->em->getRepository(IvyTransaction::class)->findByReference($referenceId);
            if (!$transaction) {
                $order = $this->em->getRepository(\Shopware\Models\Order\Order::class)->findOneBy(['number' => $referenceId]);
                if ($order) {
                    $transaction = $this->em->getRepository(IvyTransaction::class)->findOneBy(['orderId' => $order->getId()]);
                }
            }
            if ($transaction) {
                /** @var \IvyPaymentPlugin\Service\StoreProxy $storeProxy */
                $storeProxy = $this->container->get('ivy_store_proxy');
                try {
                    $swContextToken = $transaction->getSwContextToken();
                    $proxyResponse = $storeProxy->proxy($this->request, $swContextToken);
                } catch (\Throwable $e) {
                    $this->logger->error('proxy request error');
                    $this->logger->error($e->getMessage());
                    $this->logger->error($e->getTraceAsString());
                }
                if (isset($proxyResponse)) {
                    $this->logger->info('handleWebhook received proxy response');
                    $content = (string)$proxyResponse->getBody();
                    $this->logger->debug($content);
                    $this->response->setContent($content);
                    $this->response->headers->set('Content-Type', $proxyResponse->getHeader('Content-Type'));
                }
                $this->response->send();
                $this->get('kernel')->terminate($this->request, $this->response);
                exit(0);
            }
            $this->logger->error('ivy transaction by referenceId not found ' . $referenceId);
        } catch (\Exception $e) {
            $this->logger->error('transaction update error: ' . $e->getMessage());
            $this->logger->error($e->getTraceAsString());
            return new JsonResponse(['success' => false, 'error' => 'transaction update error: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $this->logger->error('webhook unknown error');
        return new JsonResponse(['success' => false, 'error' => 'unknown error'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function createOrderAction()
    {
        $this->logger = $this->expressService->getLogger();
        $this->logger->info('create order action');
        $request = $this->Request();
        $outputData = [];
        $error = null;
        try {
            if ('ivy_payment' !== $this->getPaymentShortName()) {
                throw new IvyException('invalid payment method selected ' . $this->getPaymentShortName());
            }
            $signature = $request->get('_sw_payment_token');
            $basket = $this->loadBasketFromSignature($signature);
            $this->verifyBasketSignature($signature, $basket);

            $referenceId = $this->request->get('referenceId');

            /** @var IvyTransaction $ivyPaymentSession */
            $ivyPaymentSession = $this->em
                ->getRepository(IvyTransaction::class)->findByReference($referenceId);
            if ($ivyPaymentSession === null) {
                throw new IvyException('ivy transaction by reference ' . $referenceId . ' not found');
            }

            $orderNumber = '';
            //always prefer an existing order
            if ($ivyPaymentSession->getOrderId()) {
                try {
                    $existedOrder = $ivyPaymentSession->getOrder();
                } catch (\Exception $e) {
                    $existedOrder = null;
                }
                if ($existedOrder instanceof Order) {
                    $orderNumber = (string)$existedOrder->getNumber();
                }
            }
            $paymentStatus = IvyTransaction::STATUS_MAP[$ivyPaymentSession->getStatus()] ?: null;

            if ($orderNumber === '') {
                $orderNumber = (string)$this->saveOrder(
                    $ivyPaymentSession->getIvySessionId(),
                    $referenceId,
                    $paymentStatus
                );

                if ($orderNumber === '') {
                    throw new IvyException('can not save order');
                }

                $outputData = [
                    'success' => true,
                    'displayId' => $orderNumber,
                    'referenceId' => $orderNumber,
                    'metadata' => [
                        'shopwareOrderId' => $orderNumber
                    ]
                ];
                $this->logger->info('created order with number ' . $orderNumber);

                $this->logger->info('update ivy order');
                $ivyOrderId = $ivyPaymentSession->getIvyOrderId();
                /** @var \IvyPaymentPlugin\Service\IvyApiClient $ivyApiClient */
                $ivyApiClient = $this->container->get('ivy_api_client');
                $ivyResponse = $ivyApiClient->sendApiRequest('order/update', \json_encode([
                    'id' => $ivyOrderId,
                    'displayId' => $orderNumber,
                    'referenceId' => $orderNumber,
                    'metadata' => [
                        'shopwareOrderId' => $orderNumber
                    ]
                ]));

                $this->logger->info('ivy response: ' . \print_r($ivyResponse, true));


                $order = $this->em->getRepository(Order::class)
                    ->findOneBy(['number' => $orderNumber]);
                if (!$order instanceof Order) {
                    throw new IvyException('can not load saved order');
                }

                $ivyPaymentSession->setUpdated(new \DateTime());
                $ivyPaymentSession->setOrder($order);
                $ivyPaymentSession->setOrderNumber($orderNumber);
                $this->em->flush($ivyPaymentSession);
            } else {
                $this->logger->info('order existing: ' . $orderNumber);
            }



        }  catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());
            $error = $e->getMessage();
        }

        if ($error) {
            $outputData['success'] = false;
            $outputData['error'] = $error;
        }

        $this->logger->info('createOrder send proxy response');

        \ini_set('serialize_precision', '-1');
        $response = new IvyJsonResponse($outputData);
        $response->send();
        $this->get('kernel')->terminate($this->request, $response);
        exit(0);
    }
}
