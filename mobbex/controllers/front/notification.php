<?php
/**
 * notification.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 1.5.1
 * @see     PaymentModuleCore
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * This class receives a POST request from the PSP and creates the PrestaShop
 * order according to the request parameters
 **/
class MobbexNotificationModuleFrontController extends ModuleFrontController
{
    /*
     * Handles the Instant Payment Notification
     *
     * @return bool
     */
    public function postProcess()
    {
        // We don't do anything if the module has been disabled by the merchant
        if ($this->module->active == false) {
            die;
        }

        // Get current action
        $action = Tools::getValue('action');

        if ($action == false) {
            return Tools::redirect("index.php");
        }

        // Get Data from request
        $cart_id = Tools::getValue('id_cart');
        $customer_id = Tools::getValue('customer_id');

        //Restore the context to process the order validation properly
        $context = $this->context;
        $context->cart = new Cart((int) $cart_id);
        $context->customer = new Customer((int) $customer_id);
        $context->currency = new Currency((int) $context->cart->id_currency);
        $context->language = new Language((int) $context->customer->id_lang);

        $order = new Order((int) Order::getOrderByCartId($cart_id));

        $secure_key = $context->customer->secure_key;
        $transaction_id = Tools::getValue('transactionId');
        $status = (int) Tools::getValue('status');

        // Only validate Status 2, 3 or 200 nothing else
        if ($status == 200 || $status == 3 || $status == 2) {
            if (Validate::isLoadedObject($context->cart) && $context->cart->orderExists() == false) {
                // Hook validate order
                Hook::exec('actionValidateOrder', array(
                    'cart' => $context->cart,
                    'order' => $order,
                    'customer' => $context->customer,
                    'currency' => $context->currency,
                    'orderStatus' => (int) $result['orderStatus'],
                ));
            }

            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart_id . '&id_module=' . $this->module->id . '&id_order=' . $order->id . '&transactionId=' . $transaction_id . '&key=' . $secure_key);
        } else {
            Tools::redirect('index.php?controller=order&step=1');
        }
    }
}
