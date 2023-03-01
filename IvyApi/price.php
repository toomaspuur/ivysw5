<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\IvyApi;

class price
{
    /** @var float  */
    private $totalNet = 0.;
    /** @var float  */
    private $vat = 0.;
    /** @var float  */
    private $shipping = 0.;
    /** @var float  */
    private $total = 0.;
    /** @var float  */
    private $subTotal = 0.;
    /** @var string  */
    private $currency = '';

    /**
     * @param float $totalNet
     * @return price
     */
    public function setTotalNet($totalNet)
    {
        $this->totalNet = $totalNet;
        return $this;
    }

    /**
     * @param float $vat
     * @return price
     */
    public function setVat($vat)
    {
        $this->vat = $vat;
        return $this;
    }

    /**
     * @param float $shipping
     * @return price
     */
    public function setShipping($shipping)
    {
        $this->shipping = $shipping;
        return $this;
    }

    /**
     * @param float $total
     * @return price
     */
    public function setTotal($total)
    {
        $this->total = $total;
        return $this;
    }

    /**
     * @param string $currency
     * @return price
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @return float
     */
    public function getTotalNet()
    {
        return $this->totalNet;
    }

    /**
     * @return float
     */
    public function getVat()
    {
        return $this->vat;
    }

    /**
     * @return float
     */
    public function getShipping()
    {
        return $this->shipping;
    }

    /**
     * @return float
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @return float
     */
    public function getSubTotal()
    {
        return $this->subTotal;
    }

    /**
     * @param float $subTotal
     * @return price
     */
    public function setSubTotal($subTotal)
    {
        $this->subTotal = $subTotal;
        return $this;
    }


}
