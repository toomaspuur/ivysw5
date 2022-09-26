<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\IvyApi;

class address
{
    /** @var string  */
    private $line1 = '';
    /** @var string  */
    private $line2 = '';
    /** @var string  */
    private $city = '';
    /** @var string  */
    private $zipCode = '';
    /** @var string  */
    private $country = '';

    /**
     * @param string $line1
     * @return address
     */
    public function setLine1($line1)
    {
        $this->line1 = $line1;
        return $this;
    }

    /**
     * @param string $city
     * @return address
     */
    public function setCity($city)
    {
        $this->city = $city;
        return $this;
    }

    /**
     * @param string $zipCode
     * @return address
     */
    public function setZipCode($zipCode)
    {
        $this->zipCode = $zipCode;
        return $this;
    }

    /**
     * @param string $country
     * @return address
     */
    public function setCountry($country)
    {
        $this->country = $country;
        return $this;
    }

    /**
     * @return string
     */
    public function getLine1()
    {
        return $this->line1;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @return string
     */
    public function getZipCode()
    {
        return $this->zipCode;
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @param string $line2
     * @return address
     */
    public function setLine2($line2)
    {
        $this->line2 = $line2;
        return $this;
    }

    /**
     * @return string
     */
    public function getLine2()
    {
        return $this->line2;
    }
}
