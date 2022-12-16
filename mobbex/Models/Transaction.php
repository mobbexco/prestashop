<?php
namespace Mobbex\PS\Checkout\Models;

class Transaction extends AbstractModel
{
    public $id;
    public $cart_id;
	public $parent;
	public $payment_id;
	public $description;
	public $status_code;
	public $status;
	public $status_message;
	public $source_name;
	public $source_type;
	public $source_reference;
	public $source_number;
	public $source_expiration;
	public $source_installment;
	public $installment_name;
	public $source_url;
	public $cardholder;
	public $entity_name;
	public $entity_uid;
	public $customer;
	public $checkout_uid;
	public $total;
	public $currency;
    public $risk_analysis;
	public $data;
	public $created;
	public $updated;

    public static $definition = array(
        'table' => 'mobbex_transaction',
        'primary' => 'id',
        'multilang' => false,
        'fields' => array(
            'cart_id'            => array('type' => self::TYPE_INT, 'required' => false),
            'parent'             => array('type' => self::TYPE_BOOL, 'required' => false),
            'payment_id'         => array('type' => self::TYPE_STRING, 'required' => false),
            'description'        => array('type' => self::TYPE_STRING, 'required' => false),
            'status_code'        => array('type' => self::TYPE_STRING, 'required' => false),
            'status'             => array('type' => self::TYPE_STRING, 'required' => false),
            'status_message'     => array('type' => self::TYPE_STRING, 'required' => false),
            'source_name'        => array('type' => self::TYPE_STRING, 'required' => false),
            'source_type'        => array('type' => self::TYPE_STRING, 'required' => false),
            'source_reference'   => array('type' => self::TYPE_STRING, 'required' => false),
            'source_number'      => array('type' => self::TYPE_STRING, 'required' => false),
            'source_expiration'  => array('type' => self::TYPE_STRING, 'required' => false),
            'source_installment' => array('type' => self::TYPE_STRING, 'required' => false),
            'source_name'        => array('type' => self::TYPE_STRING, 'required' => false),
            'installment_name'   => array('type' => self::TYPE_STRING, 'required' => false),
            'source_url'         => array('type' => self::TYPE_STRING, 'required' => false),
            'cardholder'         => array('type' => self::TYPE_STRING, 'required' => false),
            'entity_name'        => array('type' => self::TYPE_STRING, 'required' => false),
            'entity_uid'         => array('type' => self::TYPE_STRING, 'required' => false),
            'customer'           => array('type' => self::TYPE_STRING, 'required' => false),
            'checkout_uid'       => array('type' => self::TYPE_STRING, 'required' => false),
            'total'              => array('type' => self::TYPE_FLOAT, 'required' => false),
            'currency'           => array('type' => self::TYPE_STRING, 'required' => false),
            'risk_analysis'      => array('type' => self::TYPE_STRING, 'required' => false),
            'data'               => array('type' => self::TYPE_STRING, 'required' => false),
            'created'            => array('type' => self::TYPE_STRING, 'required' => false),
            'updated'            => array('type' => self::TYPE_STRING, 'required' => false),
        ),
    );

    /**
     * Saves the transaction with the data
     */
    public static function saveTransaction($cart_id, $data)
    {
        $trx = new \Mobbex\PS\Checkout\Models\Transaction();

        $trx->cart_id = $cart_id;

        foreach ($data as $key => $value)
            $trx->$key = $value;

        $trx->save();
    }

    /**
     * Get the transactions from the db and returns an array of \Mobbex\PS\Checkout\Models\Transaction objects.
     * If param $parent is true, it returns only the parent webhook
     * 
     * @param int $order_id
     * @param bool $parent 
     * 
     * @return array|object
     */
    public static function getTransactions($cart_id, $parent = false)
    {
        $data = \Db::getInstance()->executes('SELECT * FROM '._DB_PREFIX_.'mobbex_transaction' . ' WHERE cart_id = ' . $cart_id . ($parent ? ' and parent = 1' : '') . ' ORDER BY id DESC');
        $transactions = [];

        foreach ($data as $value)
            $transactions[] = new \Mobbex\PS\Checkout\Models\Transaction($value['id']);

        return $parent && isset($transactions[0]) ? $transactions[0] : $transactions;
    }

    /**
     * Receives an array with the weebhook, generates the order status and returns an array with organized data
     * 
     * @param array $res
     * @return array $data
     * 
     */
    public static function formatData($res)
    {
        $data = [
            'parent'             => self::isParentWebhook($res['payment']['operation']['type']),
            'payment_id'         => isset($res['payment']['id']) ? $res['payment']['id'] : '',
            'description'        => isset($res['payment']['description']) ? $res['payment']['description'] : '',
            'status'             => (int) $res['payment']['status']['code'],
            'order_status'       => (int) \Configuration::get('MOBBEX_OS_PENDING'),
            'status_message'     => isset($res['payment']['status']['message']) ? $res['payment']['status']['message'] : '',
            'source_name'        => !empty($res['payment']['source']['name']) ? $res['payment']['source']['name'] : 'Mobbex',
            'source_type'        => !empty($res['payment']['source']['type']) ? $res['payment']['source']['type'] : 'Mobbex',
            'source_reference'   => isset($res['payment']['source']['reference']) ? $res['payment']['source']['reference'] : '',
            'source_number'      => isset($res['payment']['source']['number']) ? $res['payment']['source']['number'] : '',
            'source_expiration'  => isset($res['payment']['source']['expiration']) ? json_encode($res['payment']['source']['expiration']) : '',
            'source_installment' => isset($res['payment']['source']['installment']) ? json_encode($res['payment']['source']['installment']) : '',
            'installment_name'   => isset($res['payment']['source']['installment']['description']) ? $res['payment']['source']['installment']['description'] : '',
            'source_url'         => isset($res['payment']['source']['url']) ? json_encode($res['payment']['source']['url']) : '',
            'cardholder'         => isset($res['payment']['source']['cardholder']) ? json_encode(($res['payment']['source']['cardholder'])) : '',
            'entity_name'        => isset($res['entity']['name']) ? $res['entity']['name'] : '',
            'entity_uid'         => isset($res['entity']['uid']) ? $res['entity']['uid'] : '',
            'customer'           => isset($res['customer']) ? json_encode($res['customer']) : '',
            'checkout_uid'       => isset($res['checkout']['uid']) ? $res['checkout']['uid'] : '',
            'total'              => isset($res['payment']['total']) ? $res['payment']['total'] : '',
            'currency'           => isset($res['checkout']['currency']) ? $res['checkout']['currency'] : '',
            'risk_analysis'      => isset($res['payment']['riskAnalysis']['level']) ? $res['payment']['riskAnalysis']['level'] : '',
            'data'               => json_encode($res),
            'created'            => isset($res['payment']['created']) ? $res['payment']['created'] : '',
            'updated'            => isset($res['payment']['updated']) ? $res['payment']['created'] : '',
        ];

        // Validate mobbex status and create order status
        $state = \Mobbex\PS\Checkout\Models\OrderUpdate::getState($data['status']);

        if ($state == 'onhold') {
            $data['order_status'] = (int) \Configuration::get('MOBBEX_OS_WAITING');
        } else if ($state == 'approved') {
            $data['order_status'] =  (int) (\Configuration::get('MOBBEX_ORDER_STATUS_APPROVED') ?: \Configuration::get('PS_OS_' . 'PAYMENT'));
        } else if ($state == 'failed') {
            $data['order_status'] = (int) (\Configuration::get('MOBBEX_ORDER_STATUS_FAILED') ?: \Configuration::get('PS_OS_' . 'ERROR'));
        } else if ($state == 'refunded') {
            $data['order_status'] = (int) \Configuration::get('MOBBEX_ORDER_STATUS_REFUNDED' ?: \Configuration::get('PS_OS_' . 'REFUND'));
        } else if ($state == 'rejected') {
            $data['order_status'] = (int) (\Configuration::get('MOBBEX_ORDER_STATUS_REJECTED') ?: \Configuration::get('PS_OS_' . 'ERROR'));
        }

        return $data;
    }

    /**
     * Receives the webhook "opartion type" and return true if the webhook is parent and false if not
     * 
     * @param string $operationType
     * @return bool true|false
     * 
     */
    public static function isParentWebhook($operationType)
    {
        $config = new Config();

        if ($operationType === "payment.v2") {
            if ($config->settings['multicard'] || $config->settings['multivendor'])
                return false;
        }

        return true;
    }

    /**
     * Return the list of sources from the weebhook and filter them
     * 
     * @param array $transactions
     * @return array $sources
     * 
     */
    public static function getTransactionsSources($transactions)
    {

        $sources = [];

        foreach ($transactions as $key => $transaction) {
            if ($transaction->parent == "1" && count($transactions) > 1) {
                unset($transactions[$key]);
            } else {
                if ($transaction->source_name != 'mobbex') {

                    $sources[] = [
                        'source_type'      => $transaction->source_type,
                        'source_name'      => $transaction->source_name,
                        'source_number'    => $transaction->source_number,
                        'installment_name' => $transaction->installment_name,
                        'source_url'       => $transaction->source_url,
                        'total'            => $transaction->total,
                    ];
                }
            }
        }

        foreach ($sources as $key => $value) {
            if ($key > 0 && $value['source_number'] == $sources[0]['source_number'])
            unset($sources[$key]);
        }

        return $sources;
    }

    /**
     * Return the list of entities from the weebhook and filter them.
     * 
     * @param array $transactions
     * @return array $entities
     * 
     */
    public static function getTransactionsEntities($transactions)
    {
        $entities = [];

        foreach ($transactions as $key => $transaction) {

            if ($transaction->parent == "1" && count($transactions) > 1) {
                unset($transactions[$key]);
            } else {
                $entities[] = [
                    'entity_uid'  => $transaction->entity_uid,
                    'entity_name' => $transaction->entity_name,
                    'total'       => $transaction->total,
                    'coupon'      => self::generateCoupon($transaction)
                ];
            }
        }

        foreach ($entities as $key => $value) {
            if ($key > 0 && $value['entity_uid'] == $entities[0]['entity_uid'])
            unset($entities[$key]);
        }

        return $entities;
    }

    /**
     * Return the coupon of the transaction.
     * 
     * @param array $transactions
     * @return string $coupon
     * 
     */
    public static function generateCoupon($transaction)
    {
        $coupon = "https://mobbex.com/console/" . $transaction->entity_uid . "/operations/?oid=" . $transaction->payment_id;
        return $coupon;
    }
}
