<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\Components;

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class CustomObjectNormalizer extends ObjectNormalizer
{
    /**
     * @param $object
     * @param string|null $format
     * @param array|null $context
     * @return array
     * @throws ExceptionInterface
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $data = parent::normalize($object, $format, $context);

        return \array_filter($data, static function ($value) {
            return null !== $value;
        });
    }
}