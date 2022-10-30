<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\Components;

use Shopware\Components\BasketSignature\BasketPersister;
use Shopware\Components\BasketSignature\BasketSignatureGeneratorInterface;

trait BasketPersisterTrait
{
    /**
     * @return string
     * @throws \Exception
     */
    public function persistBasket()
    {
        /** @var BasketSignatureGeneratorInterface $generator */
        $generator = $this->get('basket_signature_generator');
        $basket = $this->session->offsetGet('sOrderVariables')->getArrayCopy();
        $signature = $generator->generateSignature($basket['sBasket'], $this->session->get('sUserId'));

        /** @var BasketPersister $persister */
        $persister = $this->get('basket_persister');
        $persister->persist($signature, $basket);

        return $signature;
    }
}