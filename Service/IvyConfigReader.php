<?php

namespace IvyPaymentPlugin\Service;

use Shopware\Components\Plugin\CachedConfigReader;
use Shopware\Models\Shop\Shop;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IvyConfigReader extends CachedConfigReader
{
    public function getByPluginName($pluginName, Shop $shop = null)
    {
        if ($shop === null) {
            $shop = Shopware()->Container()->get('shop', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        }
        return parent::getByPluginName($pluginName, $shop);
    }
}