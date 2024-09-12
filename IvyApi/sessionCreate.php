<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\IvyApi;

use Symfony\Component\DependencyInjection\ContainerInterface;

class sessionCreate
{
    /** @var string  */
    private $referenceId = '';
    /** @var string  */
    private $category = '';
    /** @var price */
    private $price;
    /** @var float  */
    private $singleNet = 0.;
    /** @var array  */
    private $lineItems = [];
    /** @var array  */
    private $shippingMethods = [];
    /** @var address */
    private $billingAddress;
    /** @var string  */
    private $verificationToken = '';
    /** @var array|null */
    private $metadata;
    /** @var bool */
    private $express = false;
    /** @var string */
    private $plugin;
    /** @var bool */
    private $handshake;
    /** @var prefill */
    private $prefill;
    /** @var string */
    private $successCallbackUrl;
    /** @var string */
    private $errorCallbackUrl;
    /** @var string */
    private $quoteCallbackUrl;
    /** @var string */
    private $webhookUrl;
    /** @var string */
    private $completeCallbackUrl;

    public function __construct()
    {
        $router = Shopware()->Container()->get('router', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        if ($router !== null) {
            $this->successCallbackUrl = $router->assemble(['module' => 'frontend', 'controller' => 'IvyPayment', 'action' => 'success']);
            $this->errorCallbackUrl = $router->assemble(['module' => 'frontend', 'controller' => 'IvyPayment', 'action' => 'error']);
            $this->quoteCallbackUrl = $router->assemble(['module' => 'frontend', 'controller' => 'IvyExpress', 'action' => 'callback']);
            $this->webhookUrl = $router->assemble(['module' => 'frontend', 'controller' => 'IvyPayment', 'action' => 'notify']);
            $this->completeCallbackUrl = $router->assemble(['module' => 'frontend', 'controller' => 'IvyExpress', 'action' => 'confirm']);
        }
    }

    /**
     * @param string $referenceId
     * @return sessionCreate
     */
    public function setReferenceId($referenceId)
    {
        $this->referenceId = $referenceId;
        return $this;
    }

    /**
     * @param string $category
     * @return sessionCreate
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @param price $price
     * @return $this
     */
    public function setPrice(price $price)
    {
        $this->price = $price;
        return $this;
    }

    /**
     * @param float $singleNet
     * @return sessionCreate
     */
    public function setSingleNet($singleNet)
    {
        $this->singleNet = $singleNet;
        return $this;
    }

    /**
     * @param lineItem $lineItem
     * @return sessionCreate
     */
    public function addLineItem(lineItem $lineItem)
    {
        $this->lineItems[] = $lineItem;
        return $this;
    }


    /**
     * @param array $lineItems
     * @return sessionCreate
     */
    public function setLineItems(array $lineItems)
    {
        $this->lineItems = $lineItems;
        return $this;
    }

    /**
     * @param shippingMethod $shippingMethod
     * @return sessionCreate
     */
    public function addShippingMethod(shippingMethod $shippingMethod)
    {
        $this->shippingMethods[] =  $shippingMethod;
        return $this;
    }

    /**
     * @return string
     */
    public function getReferenceId()
    {
        return $this->referenceId;
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @return price
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @return float
     */
    public function getSingleNet()
    {
        return $this->singleNet;
    }

    /**
     * @return array
     */
    public function getLineItems()
    {
        return $this->lineItems;
    }

    /**
     * @return array
     */
    public function getShippingMethods()
    {
        return $this->shippingMethods;
    }

    /**
     * @return address
     */
    public function getBillingAddress()
    {
        return $this->billingAddress;
    }

    /**
     * @param address $billingAddress
     * @return $this
     */
    public function setBillingAddress(address $billingAddress)
    {
        $this->billingAddress = $billingAddress;
        return $this;
    }

    /**
     * @param string $verificationToken
     * @return sessionCreate
     */
    public function setVerificationToken($verificationToken)
    {
        $this->verificationToken = $verificationToken;
        return $this;
    }

    /**
     * @return string
     */
    public function getVerificationToken()
    {
        return $this->verificationToken;
    }

    /**
     * @param array|null $metadata
     * @return sessionCreate
     */
    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @return bool
     */
    public function isExpress()
    {
        return $this->express;
    }


    /**
     * @return string
     */
    public function getPlugin()
    {
        return $this->plugin;
    }

    /**
     * @param bool $express
     * @return sessionCreate
     */
    public function setExpress($express)
    {
        $this->express = $express;
        return $this;
    }

    /**
     * @param string $plugin
     * @return sessionCreate
     */
    public function setPlugin($plugin)
    {
        $this->plugin = $plugin;
        return $this;
    }

    /**
     * @return bool
     */
    public function isHandshake()
    {
        return $this->handshake;
    }

    /**
     * @param bool $handshake
     * @return sessionCreate
     */
    public function setHandshake($handshake)
    {
        $this->handshake = $handshake;
        return $this;
    }

    /**
     * @return prefill
     */
    public function getPrefill()
    {
        return $this->prefill;
    }

    /**
     * @param prefill $prefill
     * @return sessionCreate
     */
    public function setPrefill($prefill)
    {
        $this->prefill = $prefill;
        return $this;
    }

    public function getSuccessCallbackUrl(): string
    {
        return $this->successCallbackUrl;
    }

    public function setSuccessCallbackUrl(string $successCallbackUrl): sessionCreate
    {
        $this->successCallbackUrl = $successCallbackUrl;
        return $this;
    }

    public function getErrorCallbackUrl(): string
    {
        return $this->errorCallbackUrl;
    }

    public function setErrorCallbackUrl(string $errorCallbackUrl): sessionCreate
    {
        $this->errorCallbackUrl = $errorCallbackUrl;
        return $this;
    }

    public function getQuoteCallbackUrl(): string
    {
        return $this->quoteCallbackUrl;
    }

    public function setQuoteCallbackUrl(string $quoteCallbackUrl): sessionCreate
    {
        $this->quoteCallbackUrl = $quoteCallbackUrl;
        return $this;
    }

    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(string $webhookUrl): sessionCreate
    {
        $this->webhookUrl = $webhookUrl;
        return $this;
    }

    public function getCompleteCallbackUrl(): string
    {
        return $this->completeCallbackUrl;
    }

    public function setCompleteCallbackUrl(string $completeCallbackUrl): sessionCreate
    {
        $this->completeCallbackUrl = $completeCallbackUrl;
        return $this;
    }
}
