<?php
/**
 * notification.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 2.3.0
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
        $transaction_id = Tools::getValue('transactionId');
        $status = (int) Tools::getValue('status');

        // Restore context
        $context = Context::getContext();
        $context->cart = new Cart((int) $cart_id);
        $context->customer = new Customer((int) $customer_id);

        $secure_key = $context->customer->secure_key;
        $order_id = Order::getOrderByCartId($cart_id);

        if (empty($order_id)) {
            // If order was not created, wait for webhook
            $seconds = 10;
            while ($seconds > 0 && !MobbexHelper::orderExists($cart_id)) {
                sleep(1);
                $context->cart = new Cart((int) $cart_id);
                $seconds--;
            }
        }

        // If status is ok
        if ($status > 1 && $status < 400) {
            // Redirect to order confirmation
            $url = 'index.php?controller=order-confirmation';
            $url .= '&id_cart=' . $cart_id;
            $url .= '&id_order=' . $order_id;
            $url .= '&id_module=' . $this->module->id;
            $url .= '&transactionId=' . $transaction_id;
            $url .= '&key=' . $secure_key;

            Tools::redirect($url);
        } else {
            // Go back to step 1
            Tools::redirect('index.php?controller=order&step=1');
        }
    }
}