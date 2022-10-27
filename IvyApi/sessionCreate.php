<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\IvyApi;

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

}
