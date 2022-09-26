<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\IvyApi;

class lineItem
{
    /** @var string  */
    private $name = '';
    /** @var string  */
    private $referenceId = '';
    /** @var float  */
    private $singleNet = 0.;
    /** @var float  */
    private $singleVat = 0.;
    /** @var float  */
    private $amount = 0.;
    /** @var string  */
    private $image = '';
    /** @var string  */
    private $category;
    /** @var string  */
    private $ean = '';

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
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
     * @param string $referenceId
     */
    public function setReferenceId($referenceId)
    {
        $this->referenceId = $referenceId;
        return $this;
    }

    /**
     * @return float
     */
    public function getSingleNet()
    {
        return $this->singleNet;
    }

    /**
     * @param float $singleNet
     */
    public function setSingleNet($singleNet)
    {
        $this->singleNet = $singleNet;
        return $this;
    }

    /**
     * @return float
     */
    public function getSingleVat()
    {
        return $this->singleVat;
    }

    /**
     * @param float $singleVat
     */
    public function setSingleVat($singleVat)
    {
        $this->singleVat = $singleVat;
        return $this;
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     * @return $this
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * @return string
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * @param string $image
     * @return $this
     */
    public function setImage($image)
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param string $category
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return string
     */
    public function getEan()
    {
        return $this->ean;
    }

    /**
     * @param string $ean
     * @return lineItem
     */
    public function setEan($ean)
    {
        $this->ean = $ean;
        return $this;
    }
}
