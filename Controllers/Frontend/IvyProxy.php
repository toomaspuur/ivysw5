<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

use IvyPaymentPlugin\Components\BasketPersisterTrait;
use IvyPaymentPlugin\Components\IvyJsonResponse;
use IvyPaymentPlugin\Exception\IvyException;
use IvyPaymentPlugin\IvyPaymentPlugin;
use IvyPaymentPlugin\Models\IvyTransaction;
use IvyPaymentPlugin\Service\ExpressService;
use IvyPaymentPlugin\Service\IvyPaymentHelper;
use Shopware\Bundle\CartBundle\CartPositionsMode;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Customer\Customer;

class Shopware_Controllers_Frontend_IvyProxy extends Shopware_Controllers_Frontend_Checkout implements CSRFWhitelistAware
{
    private $logger;

    /**
     * @var ModelManager
     */
    private $em;

    /**
     * @var Enlight_Controller_Router
     */
    private $router;

    /**
     * @var ExpressService
     */
    private $expressService;

    /**
     * @var IvyPaymentHelper
     */
    private $ivyHelper;

    /**
     * @var array
     */
    private $data = [];

    /**
     * @return string[]
     */
    public function getWhitelistedCSRFActions()
    {
        return ['callback', 'confirm', 'finish'];
    }

    use BasketPersisterTrait;

    /**
     * @return void
     * @throws Exception
     */
    public function preDispatch()
    {
        $payments = $this->getPayments();
        $iviEnabled = false;
        foreach ($payments as $payment) {
            if ($payment['name'] === IvyPaymentPlugin::IVY_PAYMENT_NAME) {
                $iviEnabled = true;
                break;
            }
        }
        if (!$iviEnabled) {
            $response = new IvyJsonResponse(['error' => 'ivy not allowed for cart']);
            $response->send();
            $this->get('kernel')->terminate($this->request, $response);
            exit(0);
        }

        parent::preDispatch();
        $this->em = $this->getModelManager();
        $this->router = $this->Front()->Router();
        $this->expressService = $this->container->get('ivy_express_service');
        $this->ivyHelper = $this->expressService->getIvyHelper();
        $this->logger = $this->expressService->getLogger();
        if ($this->session->offsetExists('IvyNotExpressCheckout') ) {
            $this->logger = $this->logger->withName('normal');
        } else {
            $this->logger = $this->logger->withName('express');
        }
    }

    /**
     * @throws Exception
     */
    public function postDispatch()
    {
        \ini_set('serialize_precision', '3');
        $response = new IvyJsonResponse($this->data);
        if (isset($this->data['redirect'])) {
            $response->setStatusCode(IvyJsonResponse::HTTP_FOUND);
        }
        $response->send();
        $this->get('kernel')->terminate($this->request, $response);
        exit(0);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function callbackAction()
    {
        /*
            {
              "shopperPhone": "123456789",
              "shopperEmail": "test@testivy.de",
              "appId": "323213213",
              "shipping": {
                  "shippingAddress": {
                    "firstName": "ffffuuuu",
                    "lastName": "lastname3",
                    "line1": "line13",
                    "city": "line23",
                    "zipCode": "zip3",
                    "country": "DE"
                  }
                },
              "discount": {
                "voucher": "TESTV"
              },
              "currency": "EUR"
            }
        */
        $this->logger->info('--- proxy quote callback action ----');
        $payload = $this->getPayload();
        $isValid = $this->expressService->isValidRequest($this->request);
        $this->logger->debug('signatur ' . ($isValid ? 'valid' : 'not valid'));
        if ($isValid === true) {
            $referenceId = $this->request->get('reference');
            $this->logger->info('callback reference: ' . $referenceId);
            /** @var IvyTransaction $ivyPaymentSession */
            $ivyPaymentSession = $this->em->getRepository(IvyTransaction::class)->findOneBy(['reference' => $referenceId]);
            try {
                if ($ivyPaymentSession === null) {
                    throw new IvyException('ivy transaction by reference ' . $referenceId . ' not found');
                }
                $updated =  $this->expressService->updateUser($payload);
                if (!$updated) {
                    $this->logger->debug('not updated, try to create new guest and login');
                    $customer = $this->expressService->createAndLoginQuickCustomer($payload);
                    if (!$customer instanceof Customer) {
                        throw new IvyException('cann not create customer');
                    }
                    $this->logger->info('created customer: ' .  $customer->getEmail());

                    $swContexToken = $this->expressService->generateSwContextToken();
                    $ivyPaymentSession->setSwContextToken($swContexToken);
                    $this->em->flush($ivyPaymentSession);
                }

                $userData = $this->getUserData();
                $this->logger->info('$userData[\'additional\'][\'countryShipping\']: ' . \print_r($userData['additional']['countryShipping'], true));
                // reload user data in controller
                $this->View()->assign('sUserData', $userData);


                if (isset($payload['shipping']['shippingAddress'])) {
                    try {
                        $this->data['shippingMethods'] = [];
                        $paymentId = $this->expressService->getPaymentId();
                        $sDispatches = $this->getDispatches($paymentId);
                        foreach ($sDispatches as $dispatch) {
                            $countries = $this->em->getConnection()
                                ->fetchAll('SELECT c.countryiso FROM `s_premium_dispatch_countries` dc INNER JOIN s_core_countries c ON c.id = dc.countryID WHERE c.active = 1 AND dc.dispatchID = :dispatchID',
                                    ['dispatchID' => $dispatch['id']]);
                            $countries = \array_map(static function($item) {
                                return $item['countryiso'];
                            }, $countries);
                            $this->setDispatch($dispatch['id'], $paymentId);
                            // We might change the shop context here, so we need to initialize it again
                            $this->get('shopware_storefront.context_service')->initializeShopContext();
                            $basket = $this->getBasket();
                            $this->data['shippingMethods'][] = [
                                'price'     => \round($basket['sShippingcostsWithTax'],2),
                                'name'      => $dispatch['name'],
                                'reference' => $dispatch['id'],
                                'countries' => $countries,
                            ];
                        }
                    } catch (\Exception $e) {
                        $this->logger->error('shipping callback error: ' . $e->getMessage());
                        $this->data['shippingMethods'] = [];
                    }
                }
                if (isset($payload['discount']['voucher'])) {
                    $sVoucher = (string)$payload['discount']['voucher'];
                    $this->logger->info('try to apply voucher code: ' . $sVoucher);
                    $voucher = $this->basket->sAddVoucher($sVoucher);
                    if (!empty($voucher['sErrorMessages'])) {
                        $this->logger->error(\print_r($voucher['sErrorMessages'], true));
                    }
                }
                $discountAmount = 0;
                $basket = $this->getBasket();
                foreach ($basket['content'] as $item) {
                    if ((int)$item['modus'] === 2) {
                        $discountAmount += $item['amountNumeric'];
                    }
                }
                if ($discountAmount < 0) {
                    $this->data['discount'] = [
                        'amount' => - $discountAmount,
                    ];
                    $this->data['price'] = [
                        'totalNet' => $basket['AmountNetNumeric'],
                        'vat' => $basket['AmountNumeric'] - $basket['AmountNetNumeric'],
                        'total' => $basket['AmountNumeric']
                    ];
                } else {
                    $this->data['discount'] = [];
                }

            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                $this->data = [
                    'error' => $e->getMessage(),
                    'shippingMethods' => [],
                    'discount' => [],
                ];
            }
        }
    }

    /**
     * @return void|null
     * @throws Exception
     */
    public function confirmAction()
    {
        $this->logger->info('--- proxy confirm callback action ----');
        $payload = $this->getPayload();
        $isValid = $this->expressService->isValidRequest($this->request);
        $this->logger->debug('signatur ' . ($isValid ? 'valid' : 'not valid'));
        if ($isValid === true) {
            try {
                if (empty($payload)) {
                    throw new IvyException('empty payload');
                }
                $contextToken = isset($payload['metadata']['sw-context-token']) ? $payload['metadata']['sw-context-token'] : null;
                if (empty($contextToken)) {
                    throw new IvyException('sw-context-token not provided');
                }
                $this->logger->info('start create order');

                $referenceId = isset($payload['referenceId']) ? $payload['referenceId'] : null;
                /** @var IvyTransaction $ivyPaymentSession */
                $ivyPaymentSession = $this->em->getRepository(IvyTransaction::class)->findOneBy(['reference' => $referenceId]);
                if ($ivyPaymentSession === null) {
                    throw new IvyException('ivy transaction by reference ' . $referenceId . ' not found');
                }

                if ($ivyPaymentSession->isExpress()) {
                    $this->expressService->updateUser($payload);
                    // reload user data in controller
                    $this->View()->assign('sUserData', $this->getUserData());

                    $paymentId = $this->expressService->getPaymentId();
                    $shippingMethod = $payload['shippingMethod'];
                    $this->logger->info('set shipping method:  ' . \print_r($shippingMethod, true));
                    $shippingMethodId = $shippingMethod['reference'];
                    $this->setDispatch($shippingMethodId, $paymentId);
                    $this->get('shopware_storefront.context_service')->initializeShopContext();
                    $this->expressService->validateConfirmPayload($payload, $this->getBasket());
                    $this->logger->info('confrim payload is valid');
                    parent::confirmAction();
                } else {
                    parent::confirmAction();
                    $this->expressService->validateConfirmPayload($payload, $this->getBasket());
                    $this->logger->info('confrim payload is valid');
                }

                $signature = $this->persistBasket();
                Shopware()->Session()->offsetSet('signature', $signature);
                $this->logger->info('redirect to confirm');
                $this->data = [
                    'redirect' => [
                        'controller'        => 'IvyPayment',
                        'action'            => 'express',
                        'referenceId'       => $referenceId,
                        '_sw_payment_token' => $signature
                    ]
                ];
            } catch (IvyException $e) {
                $this->logger->error($e->getMessage());
                $this->data = [
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    /**
     * @return void
     */
    public function finishAction()
    {
        $this->logger->info('--- proxy finish callback action ----');
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

}