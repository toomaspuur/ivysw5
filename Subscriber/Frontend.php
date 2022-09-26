<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\Subscriber;

use Enlight\Event\SubscriberInterface;
use IvyPaymentPlugin\IvyPaymentPlugin;
use IvyPaymentPlugin\Service\IvyPaymentHelper;

class Frontend implements SubscriberInterface
{
    /**
     * @var string
     */
    private $viewDir;

    /**
     * @var IvyPaymentHelper
     */
    private $ivyPaymentHelper;

    /**
     * @var array
     */
    private $config;

    /**
     * @param string $viewDir
     * @param IvyPaymentHelper $ivyPaymentHelper
     * @param array $config
     */
    public function __construct($viewDir, IvyPaymentHelper $ivyPaymentHelper, array $config)
    {
        $this->viewDir = $viewDir;
        $this->ivyPaymentHelper = $ivyPaymentHelper;
        $this->config = $config;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onFrontendPostDispatch',
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout' => 'onPostDispatchCheckout',
        ];
    }

    public function onPostDispatchCheckout(\Enlight_Controller_ActionEventArgs $args)
    {
        /** @var \Shopware_Controllers_Frontend_Checkout $controller */
        $controller = $args->getSubject();
        $request = $args->getRequest();
        $actionName = $request->getActionName();
        $view = $controller->View();
        $payments = $view->getAssign('sPayments');
        $userData = $view->getAssign('sUserData');
        $activePaymentId = (int)(isset($userData['additional']['user']['paymentID']) ? $userData['additional']['user']['paymentID'] : null);
        if (!\in_array($actionName, ['shippingPayment', 'confirm'])) {
            return;
        }
        foreach ($payments as $key => $payment) {
            if ($payment['name'] === IvyPaymentPlugin::IVY_PAYMENT_NAME) {
                if ((int)$payment['id'] === $activePaymentId) {
                    $view->assign('ivySelected', true);
                }
            }
        }
        $view->assign('ivyConfig', $this->config);
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     * @return void
     */
    public function onFrontendPostDispatch(\Enlight_Event_EventArgs $args)
    {
        /** @var $controller \Enlight_Controller_Action */
        $controller = $args->getSubject();
        $view = $controller->View();
        $view->assign('ivyAppId', $this->ivyPaymentHelper->getIvyAppId());
        $view->assign('ivyMcc', $this->ivyPaymentHelper->getIvyMcc());
        $view->assign('ivyBannerUrl', $this->ivyPaymentHelper->getIvyBannerUrl());
        try {
            $shop = Shopware()->Shop();
            $locale = \explode('_', $shop->getLocale()->getLocale())[0];
        } catch (\Exception $e) {
            $locale = 'en';
        }
        $view->assign('ivyLocale', $locale);
        $view->addTemplateDir($this->viewDir);
    }
}
