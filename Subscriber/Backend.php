<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\Subscriber;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Enlight\Event\SubscriberInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use IvyPaymentPlugin\Exception\IvyApiException;
use IvyPaymentPlugin\Models\IvyTransaction;
use IvyPaymentPlugin\Service\IvyApiClient;

class Backend implements SubscriberInterface
{
    private $viewDir;

    /**
     * @var IvyApiClient
     */
    private $ivyApiClient;

    /**
     * @var \Enlight_Controller_Router
     */
    private $router;

    /**
     * @var \Zend_Cache_Core
     */
    private $cache;

    /**
     * @var string
     */
    private $pluginName;

    /**
     * @param IvyApiClient $ivyApiClient
     * @param \Enlight_Controller_Router $router
     * @param \Zend_Cache_Core $cache
     * @param $pluginName
     * @param $viewDir
     */
    public function __construct(
        IvyApiClient $ivyApiClient,
        \Enlight_Controller_Router $router,
        \Zend_Cache_Core $cache,
        $pluginName,
        $viewDir
    )
    {
        $this->viewDir = $viewDir;
        $this->ivyApiClient = $ivyApiClient;
        $this->router = $router;
        $this->cache = $cache;
        $this->pluginName = $pluginName;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_Order' => 'postDispatchOrder',
            'Shopware_Controllers_Backend_Order::deleteAction::before' => 'onDeleteAction',
            'Shopware_Controllers_Backend_Config::saveFormAction::after' => 'afterSaveConfig',
            'Enlight_Controller_Action_PostDispatch_Backend_Log' => 'postDispatchLog',
        ];
    }

    /**
     * @param \Enlight_Hook_HookArgs $args
     * @return void
     * @throws IvyApiException
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Db_Statement_Exception
     */
    public function afterSaveConfig(\Enlight_Hook_HookArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Config $subject */
        $subject = $args->getSubject();
        $name = $subject->Request()->get('name');
        if ($name !== 'IvyPaymentPlugin') {
            return;
        }
        $shopIds = Shopware()->Db()->query('SELECT id FROM s_core_shops')->fetchAll(\PDO::FETCH_COLUMN);
        $cacheKey = $this->pluginName;
        $this->cache->remove($cacheKey);
        foreach ($shopIds as $shopId) {
            $cacheKey = $this->pluginName . $shopId;
            $this->cache->remove($cacheKey);
        }

        $jsonContent = \json_encode([
            'quoteCallbackUrl' => $this->router->assemble(['module' => 'frontend', 'controller' => 'IvyExpress', 'action' => 'callback']),
            'successCallbackUrl' => $this->router->assemble(['module' => 'frontend', 'controller' => 'IvyPayment', 'action' => 'success']),
            'errorCallbackUrl' => $this->router->assemble(['module' => 'frontend', 'controller' => 'IvyPayment', 'action' => 'error']),
            'completeCallbackUrl' => $this->router->assemble(['module' => 'frontend', 'controller' => 'IvyExpress', 'action' => 'confirm']),
            'webhookUrl' => $this->router->assemble(['module' => 'frontend', 'controller' => 'IvyPayment', 'action' => 'notify']),
            'privacyUrl' => $this->router->assemble(['module' => 'frontend', 'controller' => 'index', 'action' => 'index']) . '?sViewport=custom&sCustom=3',
            'tosUrl' => $this->router->assemble(['module' => 'frontend', 'controller' => 'index', 'action' => 'index']) . '?sViewport=custom&sCustom=4',
        ]);
        $this->ivyApiClient->sendApiRequest('merchant/update', $jsonContent);
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     * @return void
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function onDeleteAction(\Enlight_Event_EventArgs $args)
    {
        $orderId = (int) Shopware()->Front()->Request()->getParam('id');
        $em = Shopware()->Models();
        $transactions = $em->getRepository(IvyTransaction::class)->findBy(['orderId' => $orderId]);
        foreach ($transactions as $transaction) {
            $em->remove($transaction);
            $em->flush($transaction);
        }
    }


    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     * @return void
     */
    public function postDispatchOrder(\Enlight_Controller_ActionEventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Order $controller */
        $controller = $args->getSubject();
        $request = $controller->Request();

        $view = $controller->View();
        $view->addTemplateDir($this->viewDir);
        if ($request->getActionName() === 'index') {
            $view->extendsTemplate($this->viewDir . 'backend/ivi_payment_order/app.js');
        } elseif ($request->getActionName() === 'load') {
            $view->extendsTemplate($this->viewDir . 'backend/ivi_payment_order/view/detail/window.js');
        }
    }

    public function postDispatchLog(\Enlight_Controller_ActionEventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Order $controller */
        $controller = $args->getSubject();
        $request = $controller->Request();

        $view = $controller->View();
        $view->addTemplateDir($this->viewDir);
        if ($request->getActionName() === 'load') {
            $view->extendsTemplate($this->viewDir . 'backend/ivi_log/view/detail/window.js');
            $view->extendsTemplate($this->viewDir . 'backend/ivi_log/view/ivy/list.js');
            $view->extendsTemplate($this->viewDir . 'backend/ivi_log/store/ivy_logs.js');
            $view->extendsTemplate($this->viewDir . 'backend/ivi_log/model/ivy_logs.js');
            $view->extendsTemplate($this->viewDir . 'backend/ivi_log/store/ivy_files.js');
            $view->extendsTemplate($this->viewDir . 'backend/ivi_log/model/ivy_files.js');
        }
    }
}