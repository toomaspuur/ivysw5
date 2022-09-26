<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\IvyApi;

class shippingMethod
{
    /** @var float  */
    private $price = 0.;
    /** @var string  */
    private $name = '';
    /** @var string  */
    private $reference = '';
    /** @var array  */
    private $countries = [];

    /**
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param float $price
     * @return shippingMethod
     */
    public function setPrice($price)
    {
        $this->price = $price;
        return $this;
    }

    /**
     * @param string $name
     * @return shippingMethod
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getCountries()
    {
        return $this->countries;
    }

    /**
     * @param string $country
     * @return shippingMethod
     */
    public function addCountries($country)
    {
        $this->countries[] = $country;
        return $this;
    }

    /**
     * @return string
     */
    public function getReference()
    {
        return $this->reference;
    }

    /**
     * @param string $reference
     * @return shippingMethod
     */
    public function setReference($reference)
    {
        $this->reference = $reference;
        return $this;
    }
}
