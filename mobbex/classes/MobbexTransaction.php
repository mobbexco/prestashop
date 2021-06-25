<?php
/**
 * MobbexTransaction.php
 *
 * Transaction model
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 2.2.0
 * @see     PaymentModuleCore
 */
class MobbexTransaction extends ObjectModel
{
    public $cart_id;
    public $data;

    public static $definition = array(
        'table' => 'mobbex_transaction',
        'primary' => 'cart_id',
        'multilang' => false,
        'fields' => array(
            'cart_id' => array('type' => self::TYPE_INT, 'required' => false),
            'data' => array('type' => self::TYPE_STRING, 'required' => false),
        ),
    );

    /**
     * Saves the transaction with the data
     */
    public static function saveTransaction($cart_id, $data)
    {
        $trx = new MobbexTransaction($cart_id);

        $trx->cart_id = $cart_id;
        $trx->data = json_encode($data);

        $trx->save();
    }

    public static function getTransaction($cart_id)
    {
        $trx = new MobbexTransaction($cart_id);

        try {
            return json_decode($trx->data, true);
        } catch (Exception $ex) {
            p($ex);

            return '';
        }
    }
}
