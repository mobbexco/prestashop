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
            MobbexHelper::log('Notification On Module Inactive', $_REQUEST, true, true);

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

        $customer = new Customer($customer_id);
        $order_id = MobbexHelper::getOrderByCartId($cart_id);

        // If order was not created
        if (empty($order_id)) {
            $seconds = 10;

            // Wait for webhook
            while ($seconds > 0 && !$order_id) {
                sleep(1);
                $seconds--;
                $order_id = MobbexHelper::getOrderByCartId($cart_id);
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
                'key'           => $customer->secure_key,
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
        // Get cart id
        $cartId = Tools::getValue('id_cart');

        if (!$cartId || empty($_POST['data']))
            MobbexHelper::log('Invalid Webhook Data', $_REQUEST, true, true);

        // Get Order and transaction data
        $order = MobbexHelper::getOrderByCartId($cartId, true);
        $data  = MobbexHelper::getTransactionData($_POST['data']);

        // Save webhook data
        MobbexTransaction::saveTransaction($cartId, $data);

        // Only parent webhook can modify the order
        if ($data['parent']) {
            // Aditional webhook process
            MobbexHelper::executeHook('actionMobbexWebhook', false, $data, $cartId);

            // If Order exists
            if ($order) {
                if ($data['source_name'] != 'Mobbex' && $data['source_name'] != $order->payment)
                    $order->payment = $data['source_name'];

                // Update order status only if it was not updated recently
                if ($order->getCurrentState() != $data['order_status']) {
                    $order->setCurrentState($data['order_status']);
                    $this->updateOrderPayment($order, $data);
                }

                $order->update();
            } else {
                // Create and validate Order
                $order = MobbexHelper::createOrder($cartId, $data['order_status'], $data['source_name'], $this->module);

                if ($order)
                    $this->updateOrderPayment($order, $data);
            }
        }

        die('OK: ' . MobbexHelper::MOBBEX_VERSION);
    }

    /**
     * Update the order payment information.
     * 
     * @param Order $order
     * @param array $data Transaction data.
     * 
     * @return bool Result of update.
     */
    public function updateOrderPayment($order, $data)
    {
        if (!$order->hasPayments())
            return false;

        try {
            $payment                  = $order->getOrderPaymentCollection()[0];
            $payment->payment_method  = $data['source_name'];
            $payment->transaction_id  = $data['payment_id'] ?: null;
            $payment->card_number     = $data['source_number'] ?: null;
            $payment->card_expiration = $data['source_expiration'] ? implode('/', json_decode($data['source_expiration'], true)) : null;
            $payment->card_holder     = $data['cardholder'] ? json_decode($data['cardholder'], true)['name'] : null;
            $payment->card_brand      = $data['source_type'];

            return $payment->update();
        } catch (\Exception $e) {
            MobbexHelper::log('Error Updating Order Payment on Webhook Process: ' . $e->getMessage(), $order->id, true);
        }
    }
}