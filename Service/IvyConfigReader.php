<?php

namespace IvyPaymentPlugin\Service;

use Shopware\Components\Plugin\CachedConfigReader;
use Shopware\Models\Shop\Shop;

class IvyConfigReader extends CachedConfigReader
{
    public function getByPluginName($pluginName, Shop $shop = null)
    {
        if ($shop === null) {
            $shop = Shopware()->Container()->get('shop', 'null');
        }
        return parent::getByPluginName($pluginName, $shop);
    }
}