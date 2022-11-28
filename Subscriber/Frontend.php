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
use Shopware\Components\DependencyInjection\Bridge\Session;

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
            'Enlight_Controller_Action_PostDispatch_Frontend_Detail' => 'onDetailPostDispatch',
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout' => 'onPostDispatchCheckout',
            'Enlight_Controller_Action_PostDispatch_Frontend_Register' => 'onRegister',
        ];
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     * @return void
     * @throws \Enlight_Event_Exception
     * @throws \Enlight_Exception
     * @throws \Zend_Db_Adapter_Exception
     */
    public function onRegister(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Frontend_Register $controller */
        $controller = $args->getSubject();
        $view = $controller->View();
        $ivyBasket = Shopware()->Modules()->Basket()->sGetBasket();
        $view->assign('ivyBasket', $ivyBasket);
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     * @return void
     */
    public function onDetailPostDispatch(\Enlight_Controller_ActionEventArgs $args)
    {
        /** @var \Shopware_Controllers_Frontend_Detail $controller */
        $controller = $args->getSubject();
        $view = $controller->View();
        $sArticle = $view->getAssign('sArticle');
        $payments = Shopware()->Modules()->Admin()->sGetPaymentMeans();
        $payment = null;
        foreach ($payments as $payment) {
            if ($payment['name'] === IvyPaymentPlugin::IVY_PAYMENT_NAME) {
                break;
            }
        }
        $ivyEnabled = true;
        if ($payment && (int)$payment['active'] === 1) {
            if ($sArticle['esd'] === true && (int)$payment['esdactive'] === 0) {
                $ivyEnabled = false;
            }
        } else {
            $ivyEnabled = false;
        }
        $view->assign('ivyEnabled', $ivyEnabled);
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     * @return void
     */
    public function onPostDispatchCheckout(\Enlight_Controller_ActionEventArgs $args)
    {
        /** @var \Shopware_Controllers_Frontend_Checkout $controller */
        $controller = $args->getSubject();
        $view = $controller->View();
        $payments = $controller->getPayments();
        $userData = $view->getAssign('sUserData');
        $activePaymentId = (int)(isset($userData['additional']['user']['paymentID']) ? $userData['additional']['user']['paymentID'] : null);

        $ivyEnabled = false;
        foreach ($payments as $key => $payment) {
            if ($payment['name'] === IvyPaymentPlugin::IVY_PAYMENT_NAME) {
                $ivyEnabled = true;
                if ((int)$payment['id'] === $activePaymentId) {
                    $view->assign('ivySelected', true);
                }
            }
        }
        $view->assign('ivyError', $controller->Request()->get('ivyError'));
        $view->assign('ivyEnabled', $ivyEnabled);
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
        $view->assign('ivyMcc', $this->ivyPaymentHelper->getIvyMcc());
        $view->assign('ivyBannerUrl', $this->ivyPaymentHelper->getIvyBannerUrl());
        $view->assign('ivyButtonUrl', $this->ivyPaymentHelper->getIvyButtonUrl());
        $view->assign($this->ivyPaymentHelper->getDarkTheme());
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
