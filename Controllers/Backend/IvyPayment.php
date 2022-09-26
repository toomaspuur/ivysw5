<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

use IvyPaymentPlugin\Models\IvyTransaction;
use IvyPaymentPlugin\Service\IvyPaymentHelper;
use Monolog\Logger;
use Shopware\Models\Order\Status;

class Shopware_Controllers_Backend_IvyPayment extends Shopware_Controllers_Backend_Application
{
    protected $model = IvyTransaction::class;

    protected $alias = 'transaction';

    /**
     * @var IvyPaymentHelper
     */
    private $ivyHelper;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @throws Exception
     */
    public function preDispatch()
    {
        parent::preDispatch();
        $this->ivyHelper = $this->container->get('ivy_payment_helper');
        $this->logger = $this->ivyHelper->getLogger()->withName('backend');

        $auth = $this->get('Auth');
        $user = $auth->getIdentity();
        if ($user === null) {
            $this->logger->error('no valid identity for logged in user found');
            $this->forward('noAuth');
        } elseif (\in_array($this->request->getActionName(), ['refund', 'cancel'])) {
            $this->logger->info('backend user is ' . $user->username);
        }
    }

    /**
     * get all attachments for row order
     */
    public function getDataAction()
    {
        $response = $this->getList(
            $this->Request()->getParam('start', 0),
            $this->Request()->getParam('limit', 20),
            $this->Request()->getParam('sort', []),
            $this->Request()->getParam('filter', []),
            $this->Request()->getParams()
        );

        $this->View()->assign($response);
    }

    /**
     * @return void
     */
    public function refundAction()
    {
        $this->logger = $this->logger->withName('refund');
        $success = false;
        $data = ['errorcode' => 0, 'errormessage' => ''];

        $transactionId = $this->Request()->getParam('id');
        $amount = (float) $this->Request()->getParam('amount');

        /** @var IvyTransaction|null $transaction */
        $transaction = $this->getRepository()->find($transactionId);
        if (!$transaction || $amount < 0) {
            $this->logger->error("transaction $transactionId not found or amount not positive value");
            $data['errorcode'] = 2;
        } else {
            $this->logger->info("refund $amount for IvyAppId {$transaction->getAppId()}");
            try {
                $data = $this->ivyHelper->refund($transaction, $amount);
                $refundTransaction = new IvyTransaction();
                $em = $this->getModelManager();
                $em->persist($refundTransaction);
                $refundTransaction->setStatus((string)$data['refundStatus']);
                $refundTransaction->setAmount($amount * -1);
                $refundTransaction->setOrderId($transaction->getOrderId());
                $refundTransaction->setAppId($transaction->getAppId());
                $refundTransaction->setIvyOrderId($transaction->getIvyOrderId());
                $em->flush($refundTransaction);
                if (isset($data['orderStatus'])) {
                    $transaction->setStatus((string)$data['orderStatus']);
                    $em->flush($transaction);
                }
                $refunState = $em->getRepository(Status::class)->find(Status::PAYMENT_STATE_RE_CREDITING);
                if ($refunState) {
                    $order = $transaction->getOrder();
                    $order->setPaymentStatus($refunState);
                    $em->persist($order);
                    $em->flush();
                }
                $success = true;
            } catch (\Exception $e) {
                $message = 'exeception occurred: ' . $e->getMessage();
                $data['errorcode'] = 3;
                $data['errormessage'] = $message;
            }
        }
        $this->View()->assign(['success' => $success, 'data' => $data]);
    }

    /**
     * @param $offset
     * @param $limit
     * @param $sort
     * @param $filter
     * @param array $wholeParams
     * @return array
     */
    protected function getList($offset, $limit, $sort = [], $filter = [], array $wholeParams = [])
    {
        $list = parent::getList($offset, $limit, $sort, $filter, $wholeParams);
        $data = $list['data'];
        $refundable = 0.;
        foreach ($data as $key => $item) {
            if ($item['amount'] > 0) {
                $refundableItemKey = $key;
            }
            $refundable += $item['amount'];
        }
        if (isset($refundableItemKey)) {
            $data[$refundableItemKey]['refundable'] = $refundable;
        }
        $list['data'] = $data;
        return $list;
    }
}
