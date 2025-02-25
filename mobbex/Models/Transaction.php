<?php

namespace Mobbex\PS\Checkout\Models;

class Transaction extends AbstractModel
{
    public $id;
    public $cart_id;
    public $parent;
    public $childs;
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
            'childs'             => array('type' => self::TYPE_STRING, 'required' => false),
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

        // Remember add cart_id to data array
        $data['cart_id'] = $cart_id;

        foreach (self::$definition['fields'] as $key => $value)
            $trx->$key = isset($data[$key]) ? $data[$key] : null;

        if (!$trx->save())
            throw new \Mobbex\Exception("Error Saving Transaction for Cart #$cart_id", 1, [
                \Db::getInstance()->getNumberError(),
                \Db::getInstance()->getMsgError()
            ]);

        return $trx;
    }

    /**
     * Get the transactions from the db and returns an array of \Mobbex\PS\Checkout\Models\Transaction objects.
     * If param $parent is true, it returns only the parent webhook
     * 
     * @param int $cart_id
     * @param bool $parent 
     * 
     * @return array|object
     */
    public static function getTransactions($cart_id, $parent = false)
    {
        $data = \Db::getInstance()->executes('SELECT * FROM ' . _DB_PREFIX_ . 'mobbex_transaction' . ' WHERE cart_id = ' . $cart_id . ($parent ? ' and parent = 1' : '') . ' ORDER BY id DESC');

        if (!$parent) {
            $childs = [];
            foreach ($data as $value)
                $childs[] = new \Mobbex\PS\Checkout\Models\Transaction($value['id']);
            return $childs;
        }

        return !empty($data[0]) ? new \Mobbex\PS\Checkout\Models\Transaction($data[0]['id']) : new \Mobbex\PS\Checkout\Models\Transaction();
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
            'childs'             => isset($res['childs']) ? json_encode($res['childs']) : '',
            'parent'             => isset($res['payment']['id']) ? self::isParentWebhook($res['payment']['id']) : false,
            'payment_id'         => isset($res['payment']['id']) ? $res['payment']['id'] : '',
            'description'        => isset($res['payment']['description']) ? $res['payment']['description'] : '',
            'status_code'        => isset($res['payment']['status']['code']) ? (int) $res['payment']['status']['code'] : '',
            'status'             => isset($res['payment']['status']['code']) ? (int) $res['payment']['status']['code'] : '',
            'order_status'       => (int) \Configuration::get('MOBBEX_OS_PENDING'),
            'status_message'     => isset($res['payment']['status']['message']) ? $res['payment']['status']['message'] : '',
            'source_name'        => !empty($res['payment']['source']['name']) ? $res['payment']['source']['name'] : 'Mobbex',
            'source_type'        => !empty($res['payment']['source']['type']) ? $res['payment']['source']['type'] : 'Mobbex',
            'source_reference'   => isset($res['payment']['source']['reference']) ? $res['payment']['source']['reference'] : '',
            'source_number'      => isset($res['payment']['source']['number']) ? $res['payment']['source']['number'] : '',
            'source_expiration'  => isset($res['payment']['source']['expiration']) ? json_encode($res['payment']['source']['expiration']) : '',
            'source_installment' => isset($res['payment']['source']['installment']) ? json_encode($res['payment']['source']['installment']) : '',
            'installment_name'   => isset($res['payment']['source']['installment']['description']) ? $res['payment']['source']['installment']['description'] : '',
            'source_url'         => isset($res['payment']['source']['url']) ? $res['payment']['source']['url'] : '',
            'cardholder'         => isset($res['payment']['source']['cardholder']) ? json_encode(($res['payment']['source']['cardholder'])) : '',
            'entity_name'        => isset($res['entity']['name']) ? $res['entity']['name'] : '',
            'entity_uid'         => isset($res['entity']['uid']) ? $res['entity']['uid'] : '',
            'customer'           => isset($res['customer']) ? json_encode($res['customer']) : '',
            'checkout_uid'       => isset($res['checkout']['uid']) ? $res['checkout']['uid'] : '',
            'checkout_total'     => isset($res['checkout']['total']) ? $res['checkout']['total'] : 0,
            'total'              => isset($res['payment']['total']) ? $res['payment']['total'] : '',
            'currency'           => isset($res['checkout']['currency']) ? $res['checkout']['currency'] : '',
            'risk_analysis'      => isset($res['payment']['riskAnalysis']['level']) ? $res['payment']['riskAnalysis']['level'] : '',
            'data'               => isset($res) ? json_encode($res) : '',
            'created'            => isset($res['payment']['created']) ? $res['payment']['created'] : '',
            'updated'            => isset($res['payment']['updated']) ? $res['payment']['created'] : '',
        ];

        // Validate mobbex status and create order status
        $state = self::getState($data['status_code']);

        if ($state == 'onhold') {
            $data['order_status'] = (int) \Configuration::get('MOBBEX_OS_WAITING');
        } else if ($state == 'authorized') {
            $data['order_status'] =  (int) (\Configuration::get('MOBBEX_ORDER_STATUS_AUTHORIZED') ?: \Configuration::get('MOBBEX_OS_' . 'AUTHORIZED'));
        } else if ($state == 'approved') {
            $data['order_status'] =  (int) (\Configuration::get('MOBBEX_ORDER_STATUS_APPROVED') ?: \Configuration::get('PS_OS_' . 'PAYMENT'));
        } else if ($state == 'expired') {
            $data['order_status'] = (int) \Configuration::get('MOBBEX_OS_EXPIRED');
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
     * Check if webhook is parent type using him payment id.
     * 
     * @param string $paymentId
     * 
     * @return bool
     */
    public static function isParentWebhook($paymentId)
    {
        return strpos($paymentId, 'CHD-') !== 0;
    }

    /**
     * Return the list of sources from the weebhook and filter them
     * 
     * @param object $parent
     * @param array  $childs
     * 
     * @return array $sources
     * 
     */
    public static function getTransactionsSources($parent, $childs)
    {
        if ($parent->source_name == 'multicard' && !empty($childs))
            foreach ($childs as $child)
                $sources[] = [
                    'source_type'      => $child->source_type,
                    'source_name'      => $child->source_name,
                    'source_number'    => $child->source_number,
                    'installment_name' => $child->installment_name,
                    'total'            => $child->total,
                ];
        else
            $sources[] = [
                'source_type'      => $parent->source_type,
                'source_name'      => $parent->source_name,
                'source_number'    => $parent->source_number,
                'source_url'       => $parent->source_url,
                'installment_name' => $parent->installment_name,
                'total'            => $parent->total,
            ];
        return $sources;
    }

    /**
     * Return the list of entities from the weebhook and filter them.
     * 
     * @param object $parent
     * @param array  $childs
     * @return array $entities
     * 
     */
    public static function getTransactionsEntities($parent, $childs)
    {
        if ($parent->source_name != 'multicard' && !empty($childs))
            foreach ($childs as $child)
                $entities[] = [
                    'entity_uid'  => $child->entity_uid,
                    'entity_name' => $child->entity_name,
                ];
        else
            $entities[] = [
                'entity_uid'  => $parent->entity_uid,
                'entity_name' => $parent->entity_name,
            ];
        return $entities;
    }

    /**
     * Get childs data from webhook and create an array of child transactions(type object)
     * 
     * @return array $childs
     * 
     */
    public function getChilds()
    {
        $childs =[];
        $childrenData = is_array(json_decode($this->childs, true)) ? json_decode($this->childs ? $this->childs : '', true) : [] ;

        foreach ($childrenData as $childData)
            $childs[] = (new \Mobbex\PS\Checkout\Models\Transaction)->loadFromWebhookData($childData);
        return $childs;
    }

    /**
     * Create a formated new transaction with childs data from childs node
     * 
     * @param  array  $childData
     * @return object $this
     * 
     */
    public function loadFromWebhookData($childData)
    {
        $childData    = is_array($childData) ? $childData : [];
        $formatedData = self::formatData($childData);

        foreach ($formatedData as $key => $value)
            $this->$key = $value;
        return $this;
    }

    /**
     * Create a new transaction with childs data from webhook childs transactions
     * 
     */
    public function loadChildTransactions()
    {
        $trx = new \PrestaShopCollection('\Mobbex\PS\Checkout\Models\Transaction');
        return $trx->sqlWhere("`parent` = '0' AND `cart_id` ='" . $this->id . "'") ? $trx->getResults() : [];
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

    /**
     * Get payment state from Mobbex status code.
     * 
     * @param int|string $status
     * 
     * @return string "onhold" | "authorized" | "approved" | "refunded" | "rejected" | "failed"
     */
    public static function getState($status)
    {
        if ($status == 2 || $status == 100 || $status == 201) {
            return 'onhold';
        } else if ($status == 3){
            return 'authorized';
        } else if ($status == 4 || $status >= 200 && $status < 300) {
            return 'approved';
        } else if ($status >= 300 && $status < 400) {
            return 'processing';
        } else if ($status == 401) {
            return 'expired';
        } else if ($status == 601 || $status == 602 || $status == 605) {
            return 'refunded';
        } else if ($status == 604) {
            return 'rejected';
        } else {
            return 'failed';
        }
    }

    /** Lock Webhook logic */

    /**
     * Check if it is a duplicated request locking process execution.
     * 
     * @return bool True if is duplicated.
     */
    public function isDuplicated()
    {
        return $this->sleepProcess(
            50000, // 50 ms
            10000, //10 ms
            function () {
                return !empty($this->getDuplicated());
            }
        );
    }

    /**
     * Sleep the execution until the callback condition is met or the time runs out.
     * 
     * @param int $maxTime Max sleep time in microseconds.
     * @param int $interval Interval in microseconds to awake and test condition.
     * @param callable $condition The condition to check each cicle.
     * 
     * @return bool Last condition callback result.
     */
    public function sleepProcess($maxTime, $interval, $condition)
    {
        $coditionResult = $condition();

        while ($maxTime > 0 && !$coditionResult) {
            usleep($interval);
            $maxTime -= $interval;
            $coditionResult = $condition();
        }

        return $coditionResult;
    }

    /**
     * Retrieve all duplicated transactions from db.
     * 
     * @return array A list of rows.
     */
    public function getDuplicated()
    {
        $db = \Db::getInstance();
        return $db->executeS("SELECT `id` FROM `". _DB_PREFIX_ ."mobbex_transaction` WHERE `id`<'$this->id' AND `data`='{$db->escape($this->data)}'");
    }
}
