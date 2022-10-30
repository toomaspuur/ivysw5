<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\IvyApi;

class prefill
{
    /** @var string */
    private $email;
    /** @var string */
    private $phone;

    /**
     * @param string|null $email
     * @param string|null $phone
     */
    public function __construct($email = null, $phone = null)
    {
        $this->email = $email;
        $this->phone = $phone;
    }


    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return prefill
     */
    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @param string $phone
     * @return prefill
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;
        return $this;
    }

}