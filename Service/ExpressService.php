<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use IvyPaymentPlugin\Components\CustomObjectNormalizer;
use IvyPaymentPlugin\Exception\IvyApiException;
use IvyPaymentPlugin\Exception\IvyException;
use IvyPaymentPlugin\IvyApi\lineItem;
use IvyPaymentPlugin\IvyApi\sessionCreate;
use IvyPaymentPlugin\IvyApi\shippingMethod;
use IvyPaymentPlugin\IvyPaymentPlugin;
use IvyPaymentPlugin\Logger\IvyPaymentLogger;
use Ramsey\Uuid\Uuid;
use Shopware\Bundle\AccountBundle\Form\Account\AddressFormType;
use Shopware\Bundle\AccountBundle\Form\Account\PersonalFormType;
use Shopware\Bundle\AccountBundle\Service\RegisterServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Country\Country;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

class ExpressService
{
    /**
     * @var IvyPaymentLogger
     */
    private $logger;

    /**
     * @var IvyPaymentHelper
     */
    private $ivyHelper;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var IvyApiClient
     */
    private $ivyApiClient;

    /**
     * @var ModelManager
     */
    private $em;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var ContextServiceInterface
     */
    private $contextService;

    /**
     * @var RegisterServiceInterface
     */
    private $registerService;

    /**
     * @var \Enlight_Controller_Front
     */
    private $front;

    /**
     * @var int
     */
    private $paymentId;


    /**
     * @param IvyPaymentHelper $ivyHelper
     * @param IvyApiClient $ivyApiClient
     * @param ModelManager $em
     * @param FormFactoryInterface $formFactory
     * @param ContextServiceInterface $contextService
     * @param RegisterServiceInterface $registerService
     * @param \Enlight_Controller_Front $front
     * @param IvyPaymentLogger $logger
     * @throws Exception
     */
    public function __construct(
        IvyPaymentHelper $ivyHelper,
        IvyApiClient $ivyApiClient,
        ModelManager $em,
        FormFactoryInterface $formFactory,
        ContextServiceInterface $contextService,
        RegisterServiceInterface $registerService,
        \Enlight_Controller_Front $front,
        IvyPaymentLogger $logger
    )
    {
        $this->logger = $logger;
        $this->ivyHelper = $ivyHelper;
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new CustomObjectNormalizer()];
        $this->serializer = new Serializer($normalizers, $encoders);
        $this->ivyApiClient = $ivyApiClient;
        $this->em = $em;
        $this->connection = $em->getConnection();
        $this->formFactory = $formFactory;
        $this->contextService = $contextService;
        $this->registerService = $registerService;
        $this->front = $front;
        $this->paymentId = (int) $this->connection
            ->fetchColumn('SELECT `id` FROM s_core_paymentmeans WHERE `name`=:paymentName', [
                ':paymentName' => IvyPaymentPlugin::IVY_PAYMENT_NAME,
            ]);
    }

    /**
     * @return int
     */
    public function getPaymentId()
    {
        return $this->paymentId;
    }


    /**
     * @return IvyPaymentLogger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return IvyPaymentHelper
     */
    public function getIvyHelper()
    {
        return $this->ivyHelper;
    }


    /**
     * @param array $basket
     * @param array $dispatch
     * @param array $country
     * @return array
     * @throws IvyException
     * @throws IvyApiException
     */
    public function createExpressSession(array $basket, array $dispatch, array $country)
    {
        $session = Shopware()->Session();
        $ivyExpressSessionData = $this->getSessionCreateDataFromBasket($basket, $dispatch, $country, true);

        // remove preselected shipping
        $ivyExpressSessionData->getPrice()->setShipping(0);
        $referenceId = Uuid::uuid4()->toString();
        $ivyExpressSessionData->setReferenceId($referenceId);
        $ivyExpressSessionData->setExpress(true);
        $ivyExpressSessionData->setMetadata([
            'sw-context-token' => $session->get('sessionId')
        ]);

        // add plugin version as string to know whether to redirect to confirmation page after ivy checkout
        $ivyExpressSessionData->setPlugin($this->ivyHelper->getVersion());
        $jsonContent = $this->serializer->serialize($ivyExpressSessionData, 'json');
        $response = $this->ivyApiClient->sendApiRequest('checkout/session/create', $jsonContent);
        if (empty($response['redirectUrl'])) {
            throw new IvyApiException('cannot obtain ivy redirect url');
        }
        return $response;
    }

    /**
     * @param array $payload
     * @return bool
     * @throws Exception
     * @throws IvyException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function updateUser(array $payload)
    {
        $session = Shopware()->Session();
        $userId = (int)$session->offsetGet('sUserId');
        if ($userId === 0) {
            return false;
        }
        $customer = $this->em->getRepository(Customer::class)->find($userId);
        if (!$customer instanceof Customer) {
            return false;
        }
        $customerData = $this->prepareUserData($payload);

        if (isset($customerData['billing']['email']) && (string)$customerData['billing']['email'] !== '') {
            $customer->setEmail($customerData['billing']['email']);
        }
        $customer->setSalutation($customerData['billing']['salutation']);
        $customer->setFirstname($customerData['billing']['firstname']);
        $customer->setLastname($customerData['billing']['lastname']);
        $customer->setPaymentId($this->paymentId);

        $shippingAddress = $customer->getDefaultShippingAddress();
        if ($shippingAddress === null) {
            $shippingAddress = new Address();
            $this->em->persist($shippingAddress);
        }
        $shippingAddress->setSalutation($customerData['shipping']['salutation']);
        $shippingAddress->setFirstname($customerData['shipping']['firstname']);
        $shippingAddress->setLastname($customerData['shipping']['lastname']);
        $shippingAddress->setStreet($customerData['shipping']['street']);
        $shippingAddress->setAdditionalAddressLine1($customerData['shipping']['additionalAddressLine1']);
        $shippingAddress->setZipcode($customerData['shipping']['zipcode']);
        $shippingAddress->setPhone($customerData['shipping']['phone']);
        $shippingAddress->setCity($customerData['shipping']['city']);
        $country = $this->em->getRepository(Country::class)->find($customerData['shipping']['country']);
        if ($country === null) {
            throw new IvyException('shipping country not allowed');
        }
        $shippingAddress->setCountry($country);


        $billingAddress = $customer->getDefaultBillingAddress();
        if ($billingAddress === null) {
            $billingAddress = new Address();
            $this->em->persist($billingAddress);
        }
        $billingAddress->setSalutation($customerData['billing']['salutation']);
        $billingAddress->setFirstname($customerData['billing']['firstname']);
        $billingAddress->setLastname($customerData['billing']['lastname']);
        $billingAddress->setStreet($customerData['billing']['street']);
        $billingAddress->setAdditionalAddressLine1($customerData['billing']['additionalAddressLine1']);
        $billingAddress->setZipcode($customerData['billing']['zipcode']);
        $billingAddress->setPhone($customerData['billing']['phone']);
        $billingAddress->setCity($customerData['billing']['city']);
        $country = $this->em->getRepository(Country::class)->find($customerData['billing']['country']);
        if ($country === null) {
            throw new IvyException('billing country not allowed');
        }
        $billingAddress->setCountry($country);

        $this->em->flush();

        $this->em->refresh($shippingAddress);
        $this->em->refresh($customer);

        $this->refreshSession($shippingAddress);
        return true;
    }

    /**
     * @param Address $address
     * @return void
     */
    private function refreshSession(Address $address)
    {
        $country = $address->getCountry();
        $countryId = $country->getId();
        $this->logger->info('set country in session ' . $country->getIso() . '(' .  $countryId . ')');
        $stateId = $address->getState() ? $address->getState()->getId() : null;
        $areaId = $address->getCountry()->getArea() ? $address->getCountry()->getArea()->getId() : null;

        Shopware()->Session()->offsetSet('sCountry', $countryId);
        Shopware()->Session()->offsetSet('sState', $stateId);
        Shopware()->Session()->offsetSet('sArea', $areaId);
        $this->logger->info('initialize Shop Context');
        Shopware()->Container()->get('shopware_storefront.context_service')->initializeShopContext();
    }

    /**
     * @param array $payload
     * @return Customer
     * @throws \Doctrine\DBAL\Exception
     */
    public function createAndLoginQuickCustomer(array $payload)
    {
        $customerData = $this->prepareUserData($payload);
        $customer = new Customer();
        $form = $this->formFactory->create(PersonalFormType::class, $customer);
        $form->submit($customerData['billing']);
        $customer->setPaymentId($this->paymentId);

        $billingAddress = new Address();
        $form = $this->formFactory->create(AddressFormType::class, $billingAddress);
        $form->submit($customerData['billing']);

        $shippingAddress = new Address();
        $form = $this->formFactory->create(AddressFormType::class, $shippingAddress);
        $form->submit($customerData['shipping']);

        $context = $this->contextService->getShopContext();
        $shop = $context->getShop();

        $this->registerService->register($shop, $customer, $billingAddress, $shippingAddress);

        $request = $this->front->Request();
        if (null !== $request) {
            $request->setPost('email', $customer->getEmail());
            $request->setPost('passwordMD5', $customer->getPassword());
            Shopware()->Modules()->Admin()->sLogin(true);

            // Set country and area to session, so the cart will be calculated correctly,
            // e.g. the country changed and has different taxes
            $this->refreshSession($shippingAddress);
        }
        return $customer;
    }

    /**
     * @return string
     */
    public function generateSwContextToken()
    {
        $shopID = Shopware()->Shop()->getId();
        $sessionId = Shopware()->Session()->get('sessionId');
        $cookies = \json_encode(['session-' . $shopID => $sessionId]);
        $this->logger->debug('generate context token from: ' . $cookies);
        $swContexToken = \base64_encode($cookies);
        $this->logger->info('new context token: ' . $swContexToken);
        return $swContexToken;
    }

    /**
     * @param array $address
     * @return int
     * @throws Exception
     */
    private function getCountryIdFromAddress(array $address)
    {
        $sql = 'SELECT id FROM s_core_countries WHERE countryiso=:iso AND active = 1';
        return (int) $this->connection->fetchColumn($sql, ['iso' => $address['country']]);
    }

    /**
     * @param array $data
     * @return array
     * @throws Exception
     */
    private function prepareUserData(array $data)
    {
        $shippingAddress = [];
        if (isset($data['shippingAddress'])) {
            $shippingAddress = $data['shippingAddress'];
        } elseif (isset($data['shipping']['shippingAddress'])) {
            $shippingAddress = $data['shipping']['shippingAddress'];
        }
        if (isset($data['billingAddress'])) {
            $billingAddress = $data['billingAddress'];
        } else {
            $billingAddress = $shippingAddress;
        }

        $this->logger->debug('recived address: ' . \print_r($billingAddress, true));
        if (\is_array($billingAddress) && !empty($billingAddress)) {
            $billingCountryId = $this->getCountryIdFromAddress($billingAddress);
            $shippingCountryId = $this->getCountryIdFromAddress($shippingAddress);

            $salutation = IvyPaymentPlugin::SALUTATION_NA;

            $customerBillingData = [
                'email' => $data['shopperEmail'],
                'accountmode' => Customer::ACCOUNT_MODE_FAST_LOGIN,
                'salutation' => $salutation,
                'firstname' => $billingAddress['firstName'] ?: '',
                'lastname' => $billingAddress['lastName'] ?: '',
                'street' => $billingAddress['line1'] ?: '',
                'additionalAddressLine1' => $billingAddress['line2'] ?: '',
                'zipcode' => $billingAddress['zipCode'] ?: '',
                'phone' => $data['shopperPhone'] ?: '',
                'city' => $billingAddress['city'] ?: '',
                'country' => $billingCountryId,
            ];

            $customerShippingData = [
                'salutation' => $salutation,
                'firstname' => $shippingAddress['firstName'] ?: '',
                'lastname' => $shippingAddress['lastName'] ?: '',
                'street' => $shippingAddress['line1'] ?: '',
                'additionalAddressLine1' => $shippingAddress['line2'] ?: '',
                'zipcode' => $shippingAddress['zipCode'] ?: '',
                'phone' => $data['shopperPhone'] ?: '',
                'city' => $shippingAddress['city'] ?: '',
                'country' => $shippingCountryId,
            ];

            return [
                'billing'  => $customerBillingData,
                'shipping' => $customerShippingData,
            ];
        }
        return [];
    }


    /**
     * @throws \Exception
     */
    public function isValidRequest(\Enlight_Controller_Request_RequestHttp $request)
    {
        $body = $request->getRawBody();
        $XIvySignature = (string)$request->getHeader('X-Ivy-Signature');
        $this->logger->debug('X-Ivy-Signature: ' . $XIvySignature);
        return $this->ivyHelper->validateNotification($XIvySignature, $body);
    }

    /**
     * @param array $basket
     * @param array $dispatch
     * @param array $country
     * @param bool $skipShipping
     * @return sessionCreate
     */
    private function getSessionCreateDataFromBasket(array $basket, array $dispatch, array $country, $skipShipping)
    {
        $ivyMcc = (string)$this->ivyHelper->getIvyMcc();
        $price = $this->ivyHelper->getPriceFromCart($basket, $skipShipping);

        $ivyLineItems = $this->ivyHelper->getLineItemFromCart($basket);
        $shippingMethod = new shippingMethod();
        $shippingMethod
            ->setPrice($basket['sShippingcostsWithTax'])
            ->setName($dispatch['name'])
            ->addCountries($country['countryiso']);

        $ivySessionData = new sessionCreate();
        $ivySessionData->setPrice($price)
            ->setLineItems($ivyLineItems)
            ->setCategory($ivyMcc)
            ->addShippingMethod($shippingMethod);
        return $ivySessionData;
    }

    /**
     * @param array $payload
     * @param array $basket
     * @param $useTotalVat
     * @return void
     * @throws IvyException
     */
    public function validateConfirmPayload(array $payload, array $basket, $useTotalVat = true)
    {
        $price = $this->ivyHelper->getPriceFromCart($basket);
        $shippingTotal = $price->getShipping();
        $total = $price->getTotal();
        $totalNet = $price->getTotalNet();
        if ($useTotalVat) {
            $vat = $price->getVat();
        } else {
            $vat = $total - $shippingTotal - $totalNet;
        }


        $violations = [];
        $accuracy = 0.0001;

        if (\abs($total - $payload['price']['total']) > $accuracy) {
            $violations[] = '$payload["price"]["total"] is ' . $payload['price']['total'] . ' waited ' . $total;
        }
        if (\abs($shippingTotal - $payload['price']['shipping']) > $accuracy) {
            $violations[] = '$payload["price"]["shipping"] is ' . $payload['price']['shipping'] . ' waited ' . $shippingTotal;
        }
        if ($basket['sCurrencyName'] !== $payload['price']['currency']) {
            $violations[] = '$payload["price"]["currency"] is ' . $payload['price']['currency'] . ' waited ' . $basket['sCurrencyName'];
        }

        $payloadLineItems = isset($payload['lineItems']) ? $payload['lineItems'] : [];
        if (empty($payloadLineItems) || !\is_array($payloadLineItems)) {
            $violations[] = 'checkout confirm with empty line items';
        }
        foreach ($payloadLineItems as $key => $payloadLineItem) {
            /** @var lineItem $lineItem */
            $lineItem = $basket['content'][$key];
            $quantity = $lineItem['quantity'];
            
            if ($lineItem['ordernumber'] !== $payloadLineItem['referenceId']) {
                $violations[] = '$payloadLineItem["referenceId"] is ' . $payloadLineItem["referenceId"] . ' waited ' . $lineItem['ordernumber'];
            }

            if ((int)$quantity !== (int)$payloadLineItem['quantity']) {
                $violations[] = '$payloadLineItem["quantity"] is ' . $payloadLineItem["quantity"] . ' waited ' . $quantity;
            }
        }
        if (!empty($violations)) {
            throw new IvyException(\print_r($violations, true));
        }
    }
}