<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\Models;

use Shopware\Components\Model\ModelEntity;
use Doctrine\ORM\Mapping as ORM;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

/**
 * @ORM\Table(name="ivy_transaction")
 * @ORM\Entity(repositoryClass="IvyTransactionRepository")
 */
class IvyTransaction extends ModelEntity
{
    const PAYMENT_STATUS_FAILED = 'failed';
    const PAYMENT_STATUS_CANCELED = 'canceled';
    const PAYMENT_STATUS_PROCESSING = 'processing';
    const PAYMENT_STATUS_REQ_ACTION = 'requires_action';
    const PAYMENT_STATUS_REQ_CONFIRM = 'requires_confirmation';
    const PAYMENT_STATUS_REQ_METHOD = 'requires_payment_method';
    const PAYMENT_STATUS_SUCCESSED = 'succeeded';

    const STATUS_CREATED = 'createOrder';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELED = 'canceled';
    const STATUS_PROCESS = 'processing';
    const STATUS_AUTH = 'authorised';
    const STATUS_PAID = 'paid';
    const STATUS_DISPUTED = 'disputed';
    const STATUS_IN_REFUND = 'in_refund';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_IN_DISPUTE = 'in_dispute';

    const STATUS_MAP = [
        self::STATUS_FAILED              => Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED,
        self::STATUS_CANCELED            => Status::PAYMENT_STATE_THE_PROCESS_HAS_BEEN_CANCELLED,
        self::STATUS_PROCESS             => Status::PAYMENT_STATE_OPEN,
        self::STATUS_AUTH                => Status::PAYMENT_STATE_COMPLETELY_PAID,
        self::STATUS_PAID                => Status::PAYMENT_STATE_COMPLETELY_PAID,
        self::STATUS_IN_DISPUTE          => Status::PAYMENT_STATE_REVIEW_NECESSARY,
        self::STATUS_DISPUTED            => Status::PAYMENT_STATE_RE_CREDITING,
        self::STATUS_IN_REFUND           => Status::PAYMENT_STATE_REVIEW_NECESSARY,
        self::STATUS_REFUNDED            => Status::PAYMENT_STATE_RE_CREDITING,
    ];

    public function __construct()
    {
        $this->created = $this->updated = new \DateTime();
    }

    /**
     * Primary Key - autoincrement value
     *
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="orderID", type="integer", nullable=true)
     */
    private $orderId;

    /**
     * @ORM\ManyToOne(targetEntity="\Shopware\Models\Order\Order")
     * @ORM\JoinColumn(name="orderID", referencedColumnName="id")
     *
     * @var Order
     */
    protected $order;

    /**
     * @var float
     *
     * @ORM\Column(name="amount", type="float", nullable=false)
     *
     */
    private $amount;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=255, nullable=true)
     */
    private $status;

    /**
     * @var string
     *
     * @ORM\Column(name="app_id", type="string", length=255, nullable=true)
     */
    private $appId;

    /**
     * @var string
     *
     * @ORM\Column(name="ivy_session_id", type="string", length=255, nullable=true)
     */
    private $ivySessionId;

    /**
     * @var string
     *
     * @ORM\Column(name="ivy_order_id", type="string", length=255, nullable=true)
     */
    private $ivyOrderId;

    /**
     * @var string
     *
     * @ORM\Column(name="reference", type="string", length=255, nullable=true)
     */
    private $reference;

    /**
     * @var string
     *
     * @ORM\Column(name="sw_payment_token", type="string", length=255, nullable=true)
     */
    private $swPaymentToken;

    /**
     * @var string
     *
     * @ORM\Column(name="initial_session_id", type="string", length=128, nullable=true)
     */
    private $initialSessionId;

    /**
     * @var string
     *
     * @ORM\Column(name="sw_context_token", type="string", length=255, nullable=true))
     */
    private $swContextToken;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated", type="datetime", nullable=true)
     */
    private $updated;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false)
     */
    private $created;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @param int $orderId
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * @return Order|null
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param Order $order
     */
    public function setOrder(Order $order)
    {
        $this->order = $order;
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return string
     */
    public function getAppId()
    {
        return $this->appId;
    }

    /**
     * @param string $appId
     */
    public function setAppId($appId)
    {
        $this->appId = $appId;
    }

    /**
     * @return string
     */
    public function getSwPaymentToken()
    {
        return $this->swPaymentToken;
    }

    /**
     * @param string $swPaymentToken
     */
    public function setSwPaymentToken($swPaymentToken)
    {
        $this->swPaymentToken = $swPaymentToken;
    }

    /**
     * @return \DateTime
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * @param \DateTime $updated
     */
    public function setUpdated(\DateTime $updated)
    {
        $this->updated = $updated;
    }

    /**
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @param \DateTime $created
     */
    public function setCreated(\DateTime $created)
    {
        $this->created = $created;
    }

    /**
     * @return string
     */
    public function getIvySessionId()
    {
        return $this->ivySessionId;
    }

    /**
     * @param string $ivySessionId
     */
    public function setIvySessionId($ivySessionId)
    {
        $this->ivySessionId = $ivySessionId;
    }

    /**
     * @return string
     */
    public function getIvyOrderId()
    {
        return (string)$this->ivyOrderId;
    }

    /**
     * @param string $ivyOrderId
     */
    public function setIvyOrderId($ivyOrderId)
    {
        $this->ivyOrderId = $ivyOrderId;
    }

    /**
     * @return string
     */
    public function getReference()
    {
        return $this->reference;
    }

    /**
     * @param string $reference
     */
    public function setReference($reference)
    {
        $this->reference = $reference;
    }

    /**
     * @return string
     */
    public function getSwContextToken()
    {
        return $this->swContextToken;
    }

    /**
     * @param string $swContextToken
     */
    public function setSwContextToken($swContextToken)
    {
        $this->swContextToken = $swContextToken;
    }

    /**
     * @return string
     */
    public function getInitialSessionId()
    {
        return $this->initialSessionId;
    }

    /**
     * @param string $initialSessionId
     * @return IvyTransaction
     */
    public function setInitialSessionId($initialSessionId)
    {
        $this->initialSessionId = $initialSessionId;
        return $this;
    }

}
