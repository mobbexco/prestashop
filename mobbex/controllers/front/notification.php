<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MobbexNotificationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        // We don't do anything if the module has been disabled by the merchant
        if ($this->module->active == false)
            die;

        // Get current action
        $action = Tools::getValue('action');

        if ($action == 'return') {
            return $this->callback();
        } else if ($action == 'webhook') {
            return $this->webhook();
        }
    }

    /**
     * Handles the redirect after payment.
     */
    public function callback()
    {
        // Get Data from request
        $cart_id        = (int) Tools::getValue('id_cart');
        $customer_id    = (int) Tools::getValue('customer_id');
        $transaction_id = Tools::getValue('transactionId');
        $status         = Tools::getValue('status');

        // Restore context
        $context           = Context::getContext();
        $context->cart     = new Cart($cart_id);
        $context->customer = new Customer($customer_id);

        $order_id = MobbexHelper::getOrderByCartId($cart_id);

        // If order was not created
        if (empty($order_id)) {
            $seconds = 10;

            // Wait for webhook
            while ($seconds > 0 && !MobbexHelper::getOrderByCartId($cart_id)) {
                sleep(1);
                $seconds--;
            }
        }

        // If status is ok
        if ($status > 1 && $status < 400) {
            // Redirect to order confirmation
            Tools::redirect('index.php?controller=order-confirmation&' . http_build_query([
                'id_cart'       => $cart_id,
                'id_order'      => $order_id,
                'id_module'     => $this->module->id,
                'transactionId' => $transaction_id,
                'key'           => $context->customer->secure_key,
            ]));
        } else {
            // Go back to checkout
            Tools::redirect('index.php?controller=order&step=1');
        }
    }

    /**
     * Handles the payment notification.
     */
    public function webhook()
    {
        // Get data from request
        $cartId      = Tools::getValue('id_cart');
        $res         = [];
        parse_str(file_get_contents('php://input'), $res);

        if (empty($cartId) || empty($res))
            die('WebHook Error: Empty cart_id or Mobbex json data. ' . MobbexHelper::MOBBEX_VERSION);
        
            
        // Get Order and transaction data
        $order = MobbexHelper::getOrderByCartId($cartId, true);
        $data  = MobbexHelper::getTransactionData($res['data']);
        
        if ( !$data['parent']) {
            //Save child webhook data
            MobbexTransaction::saveTransaction($cartId, $data);
            return;
        }

        // Save parent webhook data
        MobbexTransaction::saveTransaction($cartId, $data);
            
        // If Order exists
        if ($order) {
            // If it was not updated recently
            if ($order->getCurrentState() != $data['order_status']) {
                // Update order status
                $order->setCurrentState($data['order_status']);
                $order->save();
            }
        } else {
            // Create and validate Order
            MobbexHelper::createOrder($cartId, $data, $this->module);
        }

        die('OK: ' . MobbexHelper::MOBBEX_VERSION);
    }
}