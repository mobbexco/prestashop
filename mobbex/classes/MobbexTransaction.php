<?php
/**
 * MobbexTransaction.php
 *
 * Transaction model
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 2.4.3
 * @see     PaymentModuleCore
 */
class MobbexTransaction extends ObjectModel
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
            'created'               => array('type' => self::TYPE_STRING, 'required' => false),
            'updated'               => array('type' => self::TYPE_STRING, 'required' => false),
        ),
    );

    /**
     * Saves the transaction with the data
     */
    public static function saveTransaction($cart_id, $data)
    {
        $trx = new MobbexTransaction();

        $trx->cart_id            = $cart_id;
        $trx->parent             = $data['parent']; 
        $trx->payment_id         = $data['payment_id'];
        $trx->description        = $data['description'];
        $trx->status_code        = $data['status'];
        $trx->status             = $data['order_status'];
        $trx->status_message     = $data['status_message'];
        $trx->source_name        = $data['source_name'];
        $trx->source_type        = $data['source_type'];
        $trx->source_reference   = $data['source_reference'];
        $trx->source_number      = $data['source_number'];
        $trx->source_expiration  = $data['source_expiration'];
        $trx->source_installment = $data['source_installment'];
        $trx->installment_name   = $data['installment_name'];
        $trx->source_url         = $data['source_url'];
        $trx->cardholder         = $data['cardholder'];
        $trx->entity_name        = $data['entity_name'];
        $trx->entity_uid         = $data['entity_uid'];
        $trx->customer           = $data['customer'];
        $trx->checkout_uid       = $data['checkout_uid'];
        $trx->total              = $data['total'];
        $trx->currency           = $data['currency'];
        $trx->risk_analysis      = $data['risk_analysis'];
        $trx->data               = $data['data'];
        $trx->created            = $data['created'];
        $trx->updated            = $data['updated'];

        $trx->save();
    }

    /**
     * Get the transactions from the db and returns an array of MobbexTransaction objects.
     * If param $parent is true return only the parent webhook
     * 
     * @param int $order_id
     * @param bool $parent 
     * 
     * @return array 
     */
    public static function getTransactions($cart_id, $parent = false)
    {

        if($parent) {
            $trx = Db::getInstance()->executes('SELECT * FROM '._DB_PREFIX_.'mobbex_transaction' . ' WHERE cart_id=' . $cart_id . ' and parent=1');
            $trx = new MobbexTransaction($trx[0]['id']);
            return $trx; 
        }
        
        $data = Db::getInstance()->executes('SELECT * FROM '._DB_PREFIX_.'mobbex_transaction' . ' WHERE cart_id = ' . $cart_id);
        $transactions = [];

        foreach ($data as $value) {
            $transactions[] = new MobbexTransaction($value['id']);
        }

        try {
            return $transactions;
        } catch (Exception $ex) {
            p($ex);

            return [];
        }
    }
}
