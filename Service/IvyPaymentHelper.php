<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use IvyPaymentPlugin\Components\CustomObjectNormalizer;
use IvyPaymentPlugin\Exception\IvyApiException;
use IvyPaymentPlugin\Exception\IvyException;
use IvyPaymentPlugin\IvyApi\address;
use IvyPaymentPlugin\IvyApi\lineItem;
use IvyPaymentPlugin\IvyApi\prefill;
use IvyPaymentPlugin\IvyApi\price;
use IvyPaymentPlugin\IvyApi\sessionCreate;
use IvyPaymentPlugin\IvyApi\shippingMethod;
use IvyPaymentPlugin\Models\IvyTransaction;
use Monolog\Logger;
use Ramsey\Uuid\Uuid;
use Shopware\Models\Country\Country;
use Shopware\Models\Order\Order;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class IvyPaymentHelper
{
    const MCC_DEFAULT = '5712';
    const LIVE_URL= 'https://api.getivy.de/api/service/';
    const TEST_URL= 'https://api.sand.getivy.de/api/service/';
    const LIVE_BANNER = 'https://cdn.getivy.de/banner.js';
    const TEST_BANNER = 'https://cdn.sand.getivy.de/banner.js';
    const LIVE_BUTTON = 'https://cdn.getivy.de/button.js';
    const TEST_BUTTON = 'https://cdn.sand.getivy.de/button.js';
    /**
     * @var mixed
     */
    private $ivyServiceUrl;

    /**
     * @var mixed
     */
    private $ivyApiKey;

    /**
     * @var mixed
     */
    private $ivyMcc;

    /**
     * @var mixed
     */
    private $ivyWebhookSecret;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var mixed|string
     */
    private $ivyBannerUrl;

    /**
     * @var mixed|string
     */
    private $ivyButtonUrl;

    /**
     * @var string
     */
    private $version;

    /**
     * @var IvyApiClient
     */
    private $ivyApiClient;

    /**
     * @param array $config
     * @param IvyApiClient $ivyApiClient
     * @param Logger $logger
     */
    public function __construct(
        array $config,
        IvyApiClient $ivyApiClient,
        Logger $logger
    )
    {
        $isSandboxActive = (int)$config["isSandboxActive"] === 1;
        $this->logger = $logger;
        if ($isSandboxActive) {
            $this->ivyServiceUrl = isset($config["IvyApiUrlSandbox"]) ? $config["IvyApiUrlSandbox"] : self::TEST_URL;
            $this->ivyBannerUrl = isset($config["IvyBannerUrlSandbox"]) ? $config["IvyBannerUrlSandbox"] : self::TEST_BANNER;
            $this->ivyButtonUrl = isset($config["IvyBannerUrlSandbox"]) ? $config["IvyBannerUrlSandbox"] : self::TEST_BUTTON;
            $this->ivyApiKey = $config["SandboxIvyApiKey"];
            $this->ivyWebhookSecret = $config["SandboxIvyWebhookSecret"];
        } else {
            $this->ivyServiceUrl = self::LIVE_URL;
            $this->ivyBannerUrl =  self::LIVE_BANNER;
            $this->ivyButtonUrl =  self::LIVE_BUTTON;
            $this->ivyApiKey = $config["IvyApiKey"];
            $this->ivyWebhookSecret = $config["IvyWebhookSecret"];
        }

        $this->ivyMcc = self::MCC_DEFAULT;;

        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new CustomObjectNormalizer()];
        $this->serializer = new Serializer($normalizers, $encoders);
        $pluginName = Shopware()->Container()->getParameter('ivy_payment_plugin.plugin_name');
        $this->version = 'sw5' . Shopware()->Container()->get('dbal_connection')->executeQuery("SELECT version FROM s_core_plugins WHERE name = :name", ['name' => $pluginName])->fetchOne();
        $this->ivyApiClient = $ivyApiClient;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return mixed|string
     */
    public function getIvyButtonUrl()
    {
        return $this->ivyButtonUrl;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return mixed
     */
    public function getIvyServiceUrl()
    {
        return $this->ivyServiceUrl;
    }

    /**
     * @return mixed
     */
    public function getIvyApiKey()
    {
        return $this->ivyApiKey;
    }

    /**
     * @return mixed
     */
    public function getIvyMcc()
    {
        return $this->ivyMcc;
    }

    /**
     * @return mixed|string
     */
    public function getIvyBannerUrl()
    {
        return $this->ivyBannerUrl;
    }

    /**
     * @return mixed
     */
    public function getIvyWebhookSecret()
    {
        return $this->ivyWebhookSecret;
    }

    /**
     * @return Order
     * @throws IvyException
     */
    public function getCurrentTemporaryOrder()
    {
        $temporaryOrderId = $this->getCurrentTemporaryOrderId();
        if (null === $temporaryOrderId) {
            throw new IvyException('Temporary orderId not found in session');
        }
        return $this->getOrderByTempOrderId($temporaryOrderId);
    }

    /**
     * @param string $temporaryOrderId
     * @return Order
     * @throws IvyException
     */
    public function getOrderByTempOrderId($temporaryOrderId)
    {
        /** @var Order|null $order */
        $order = Shopware()->Models()->getRepository(Order::class)
            ->findOneBy(['temporaryId' => $temporaryOrderId]);
        if (null === $order) {
            throw new IvyException(
                \sprintf('Order with temporary id %s not found', $temporaryOrderId)
            );
        }
        return $order;
    }

    /**
     * @return mixed|null
     */
    public function getCurrentTemporaryOrderId()
    {
        $session = Shopware()->Session();
        if (null === $session) {
            return null;
        }
        return $session->get('sessionId', null);
    }

    /**
     * @param string $hash
     * @param string $body
     * @return bool
     */
    public function validateNotification($hash, $body)
    {
        $signingSecret = $this->getIvyWebhookSecret();
        $expectedSignature = \hash_hmac('sha256', $body, $signingSecret);
        if ($hash === $expectedSignature) {
            return true;
        }
        $this->logger->error('received WebHook signature: ' . $hash);
        $this->logger->error('expected WebHook signature: ' . $expectedSignature);
        $this->logger->error('WebHook Body: ' . $body);
        return false;
    }

    /**
     * @param $body
     * @return string
     */
    public function sign($body)
    {
        return \hash_hmac(
            'sha256',
            $body,
            $this->getIvyWebhookSecret()
        );
    }


    /**
     * @param Order $order
     * @param $swPaymentToken
     * @return mixed
     * @throws IvyException
     */
    public function createIvySession(Order $order, $swPaymentToken)
    {
        $data = $this->getSessionCreateDataFromOrder($order);
        $data->setMetadata([
            '_sw_payment_token' => $swPaymentToken,
            'sw-context-token' => Shopware()->Session()->getId(),
            ]);
        $data->setVerificationToken($swPaymentToken);
        $data->setHandshake(true);

        $jsonContent = $this->serializer->serialize($data, 'json');

        $this->logger->debug('create ivy session: ' . $jsonContent);

        $response = $this->ivyApiClient->sendApiRequest('checkout/session/create', $jsonContent);
        if (empty($response['redirectUrl'])) {
            throw new IvyApiException('cannot obtain ivy redirect url');
        }
        return $response;
    }

    /**
     * @param IvyTransaction $transaction
     * @param float $amount
     * @return mixed
     * @throws IvyException|GuzzleException
     */
    public function refund(IvyTransaction $transaction, $amount)
    {
        $this->logger->info('start refund ivy order: ' . $transaction->getReference());
        $data = [
            'amount' => $amount,
            'description' => 'refund from shopware',
        ];
        $ivyOrderId = (string)$transaction->getIvyOrderId();
        if ($ivyOrderId !== '') {
            $data['orderId'] = $ivyOrderId;
        } else {
            $refrence = (string)$transaction->getReference();
            $data['referenceId'] = $refrence;
        }

        $jsonContent = \json_encode($data);
        $this->logger->debug('create ivy refund: ' . $jsonContent);
        $client = new Client();
        $headers = [
            'content-type' =>'application/json',
            'X-Ivy-Api-Key' => $this->ivyApiKey,
        ];
        $options = [
            'headers' => $headers,
            'body' => $jsonContent,
        ];
        try {
            $response = $client->post($this->ivyServiceUrl . 'merchant/payment/refund', $options);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new IvyException('communication error: ' . $e->getMessage());
        }
        if ($response->getStatusCode() === 200) {
            return \json_decode((string)$response->getBody(), true);
        }
        throw new IvyException('can not refund ivy transaction: ' . $response->getStatusCode() . ' ' . $response->getBody());
    }

    /**
     * @param IvyTransaction $transaction
     * @return mixed|void
     * @throws GuzzleException
     */
    public function updateOrder(IvyTransaction $transaction)
    {
        $this->logger->info('start update ivy order: ' . $transaction->getReference());
        $order = $transaction->getOrder();
        if ($order === null) {
            $this->logger->info('skip update ivy order');
            return;
        }
        $orderNumber = (string)$order->getNumber();
        $data = [
            'id' => $transaction->getIvyOrderId(),
            'metadata' => [
                'shopwareOrderId' => $orderNumber
            ]
        ];

        $jsonContent = \json_encode($data);

        $client = new Client();
        $headers = [
            'content-type' =>'application/json',
            'X-Ivy-Api-Key' => $this->ivyApiKey,
        ];
        $options = [
            'headers' => $headers,
            'body' => $jsonContent,
        ];
        try {
            $response = $client->post($this->ivyServiceUrl . 'order/update', $options);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error('communication error: ' . $e->getMessage());
            return;
        }
        if ($response->getStatusCode() === 200) {
            return \json_decode((string)$response->getBody(), true);
        }
        $this->logger->error('can not update ivy order: ' . $response->getStatusCode() . ' ' . $response->getBody());

    }

    /**
     * @param Order $order
     * @return sessionCreate
     */
    public function getSessionCreateDataFromOrder(Order $order)
    {
        $sOrderVariables = Shopware()->Session()->get('sOrderVariables');
        $sBasket = $sOrderVariables['sBasket'];

        $ivyLineItems = $this->getLineItemFromCart($sBasket);
        $billingAddress = $this->getBillingAddress($sOrderVariables);
        $price = $this->getPriceFromCart($sBasket, false);
        $shippingMethod = $this->getShippingMethod($order, $sOrderVariables);
        $data = new sessionCreate();
        $data->setPrice($price)
            ->setLineItems($ivyLineItems)
            ->addShippingMethod($shippingMethod)
            ->setBillingAddress($billingAddress)
            ->setCategory(isset($this->ivyMcc) ? $this->ivyMcc : '')
            ->setReferenceId(Uuid::uuid4()->toString())
            ->setPlugin($this->getVersion());
        $email = $phone = null;
        if (isset($sOrderVariables['sUserData']['additional']['user']['email'])) {
            $email = $sOrderVariables['sUserData']['additional']['user']['email'];
        }
        if (isset($sOrderVariables['sUserData']['billingaddress']['phone'])) {
            $phone = $sOrderVariables['sUserData']['billingaddress']['phone'];
        }
        if ($email || $phone) {
            $data->setPrefill(new prefill($email, $phone));
        }
        return $data;
    }

    /**
     * @param \ArrayObject $sOrderVariables
     * @return address
     */
    private function getBillingAddress(\ArrayObject $sOrderVariables)
    {
        $billingAddress = new address();
        $sUserData = $sOrderVariables['sUserData'];
        $billing = $sUserData['billingaddress'];
        if ($billing) {
            $billingAddress
                ->setLine1($billing['street'])
                ->setCity($billing['city'])
                ->setZipCode($billing['zipcode'])
                ->setCountry($this->getCountryCodeFromId(
                    (int)$sUserData['billingaddress']['countryId']
                ));
        }
        return $billingAddress;
    }

    /**
     * @param array $basket
     * @param bool $skipShipping
     * @return price
     */
    public function getPriceFromCart(array $basket, $skipShipping = false)
    {
        $shippingTotal = $basket['sShippingcostsWithTax'];
        $shippingNet = $basket['sShippingcostsNet'];
        $shippingVat = $shippingTotal - $shippingNet;

        $total = $basket['sAmount'];
        $vatTotal = $basket['sAmountTax'];
        $totalNet = $total - $vatTotal - $shippingNet;
        $vat = $vatTotal - $shippingVat;

        if ($skipShipping) {
            $total -= $shippingTotal;
            $shippingTotal = 0;
        }

        $price = new price();
        $price->setTotalNet($totalNet)
            ->setVat($vat)
            ->setTotal($total)
            ->setShipping($shippingTotal)
            ->setCurrency($basket['sCurrencyName']);
        return $price;
    }

    /**
     * @param array $basket
     * @return array
     */
    public function getLineItemFromCart(array $basket)
    {
        $ivyLineItems = [];
        $ivyMcc = (string)$this->getIvyMcc();
        foreach ($basket['content'] as $swLineItem) {
            $lineItem = new lineItem();
            $singleNet = $swLineItem['netprice'];
            $singleTotal = $swLineItem['price'];
            $singleVat = $singleTotal - $singleNet;
            $quantity = $swLineItem['quantity'];

            $lineItem->setName($swLineItem['articlename'])
                ->setReferenceId($swLineItem['ordernumber'])
                ->setCategory($ivyMcc)
                ->setSingleNet($singleNet)
                ->setSingleVat($singleVat)
                ->setAmount($singleTotal * $quantity)
                ->setQuantity($quantity);

            if (isset($swLineItem['additional_details']['image']['source'])) {
                $lineItem->setImage($swLineItem['additional_details']['image']['source']);
            }
            $ivyLineItems[] = $lineItem;
        }
        return $ivyLineItems;
    }

    /**
     * @param Order $order
     * @param \ArrayObject $sOrderVariables
     * @return shippingMethod
     */
    private function getShippingMethod(Order $order, \ArrayObject $sOrderVariables)
    {
        $shippingMethod = new shippingMethod();
        $sUserData = $sOrderVariables['sUserData'];
        $shippingMethod
            ->setPrice($order->getInvoiceShipping())
            ->setName($order->getDispatch()->getName())
            ->addCountries($this->getCountryCodeFromId(
                (int)$sUserData['shippingaddress']['countryId']
            ));

        return $shippingMethod;
    }

    /**
     * @param int $id
     * @return string
     */
    private function getCountryCodeFromId($id)
    {
        /** @var Country|null $country */
        $country = Shopware()->Models()->getRepository(Country::class)->find($id);
        if (null === $country) {
            return '';
        }
        return $country->getIso();
    }
}
