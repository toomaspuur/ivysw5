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
use IvyPaymentPlugin\Models\IvyTransaction;

class Backend implements SubscriberInterface
{
    private $viewDir;

    /**
     * @param string $viewDir
     */
    public function __construct($viewDir)
    {
        $this->viewDir = $viewDir;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_Order' => 'postDispatchOrder',
            'Shopware_Controllers_Backend_Order::deleteAction::before' => 'onDeleteAction',
        ];
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

}