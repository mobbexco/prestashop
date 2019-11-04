<?php
/**
 * notification.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 1.0.0
 * @see     PaymentModuleCore
 */

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
        $transaction_id = "";

        // No transaction was made, return to order.
        // if ($transaction_id == -1) {
        // Tools::redirect('index.php?controller=order');
        // } else {
        //Restore the context to process the order validation properly
        $context = Context::getContext();
        $context->cart = new Cart((int) $cart_id);
        $context->customer = new Customer((int) $customer_id);
        $context->currency = new Currency((int) $context->cart->id_currency);
        $context->language = new Language((int) $context->customer->id_lang);

        // Create order history with status
        $amount = (float) $context->cart->getOrderTotal(true, Cart::BOTH);

        $secure_key = $context->customer->secure_key;
        $module_name = $this->module->displayName;
        $currency_id = (int) $context->currency->id;

        if ($action == "hook") {
            $body = file_get_contents('php://input');

            $res = json_decode($body, true);

            $result = MobbexHelper::evaluateTransactionData($res['form']['data']);

            // Get Transaction ID here
            $transaction_id = $result['transaction_id'];
        } elseif ($action == "return") {
            $transaction_id = Tools::getValue('transactionId');

            $result = MobbexHelper::getTransaction($context, $transaction_id);
        }

        if (!$context->cart->orderExists()) {
            $this->module->validateOrder(
                $cart_id,
                (int) $result['status'],
                $amount,
                $result['name'], // Add Card name and Installments if exist here
                $result['message'],
                array(
                    '{transaction_id}' => $transaction_id,
                    '{message}' => $result['message'],
                ), // Other data like Transaction ID
                $currency_id,
                false,
                $secure_key
            );
        }

        // Save the data
        MobbexTransaction::saveTransaction($cart_id, $result['data']);

        $order = new Order((int) Order::getOrderByCartId($cart_id));

        if ($action == 'return') {
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart_id . '&id_module=' . $this->module->id . '&id_order=' . $order->id . '&transactionId=' . $transaction_id . '&key=' . $secure_key);
        } else {
            echo "OK";
            exit;
        }
        // }
    }
}
