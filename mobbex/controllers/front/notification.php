<?php

defined('_PS_VERSION_') || exit;

use Mobbex\PS\Checkout\Models\Config;
use Mobbex\PS\Checkout\Models\Logger;

class MobbexNotificationModuleFrontController extends ModuleFrontController
{
    /** @var \Mobbex\PS\Checkout\Models\OrderUpdate */
    public $orderUpdate;

    public function postProcess()
    {
        // We don't do anything if the module has been disabled by the merchant
        if ($this->module->active == false)
            Logger::log('fatal', 'notification > postProcess | Notification On Module Inactive', $_REQUEST);

        $this->orderUpdate = new \Mobbex\PS\Checkout\Models\OrderUpdate;

        // Get current action
        $action = Tools::getValue('action');

        if ($action == 'return') {
            return $this->callback();
        } else if ($action == 'webhook') {
            return $this->webhook();
        } else if ($action == 'redirect') {
            $this->redirectAction();
        }
    }

    /**
     * Redirect to controller with a prestashop alert.
     */
    public function redirectAction()
    {
        $messages = [
            'missing_dni' => 'Debes completar tu DNI para poder continuar tu compra.'
        ];

        $type = \Tools::getValue('type');
        $url  = \Tools::getValue('url');

        //Set Notification
        $this->{$type}[] = $messages[\Tools::getValue('message')];

        //Redirect with notification
        $this->redirectWithNotifications("index.php?controller=$url");
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
        $order_id = $this->module->helper->getOrderByCartId($cart_id);

        // If status is ok
        if ($status > 1 && $status < 400) {
            // If order was not created and is creable on webhook
            if (empty($order_id)) {
                $seconds = Config::$settings['redirect_time'] ?: 10;

                // Wait for webhook
                while ($seconds > 0 && !$order_id) {
                    sleep(1);
                    $seconds--;
                    $order_id = $this->module->helper->getOrderByCartId($cart_id);
                }
            }

            // Redirect to order confirmation
            Tools::redirect('index.php?controller=order-confirmation&' . http_build_query([
                'id_cart'       => $cart_id,
                'id_order'      => $order_id,
                'id_module'     => $this->module->id,
                'transactionId' => $transaction_id,
                'key'           => $customer->secure_key,
            ]));
        } else {
            $order = $this->module->helper->getOrderByCartId($cart_id, true);

            if ($order && Config::$settings['order_first'] && Config::$settings['cart_restore']) {
                //update stock
                $this->orderUpdate->updateStock($order, Configuration::get('PS_OS_CANCELED'));
                //Cancel the order
                $order->setCurrentState(Configuration::get('PS_OS_CANCELED'));
                $order->update();
                //Restore the cart
                $cart = new Cart($cart_id);
                $this->module->helper->restoreCart($cart);
            }

            // Go back to checkout
            Tools::redirect('index.php?controller=order&step=3');
        }
    }

    /**
     * Handles the payment notification.
     */
    public function webhook()
    {
        // Get cart id
        $cartId   = Tools::getValue('id_cart');

        // Get request data
        $postData = isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' ? json_decode(file_get_contents('php://input'), true) : $_POST;

        if (!$cartId || empty($postData['data']) || empty($postData['type']))
            Logger::log('fatal', 'notification > webhook | Invalid Webhook Data', $_REQUEST);

        if ($postData['type'] !== 'checkout')
            return Logger::log('debug', 'notification > webhook | Mobbex Webhook: Ignoring Non-Checkout Webhook', $postData['type']);

        // Get Order and transaction data
        $cart  = new \Cart($cartId);
        $order = $this->module->helper->getOrderByCartId($cartId, true);
        // Get token value from query param name
        $token = Tools::getValue('mbbx_token');
        // Get formated data from $post
        $data  = \Mobbex\PS\Checkout\Models\Transaction::formatData($postData['data']);

        // Verify token
        if (!\Mobbex\Repository::validateToken($token))
            Logger::log('fatal', 'notification > webhook | Invalid Token', $_REQUEST);

        // Avoid 3xx states
        if(\Mobbex\PS\Checkout\Models\Transaction::getState($data['status_code']) == 'processing')
            $this->logger->log('fatal', 'notification > webhook | Invalid Status Code', $data);

        try {
            // Save webhook data
            $trx = \Mobbex\PS\Checkout\Models\Transaction::saveTransaction($cartId, $data);
        } catch (\Exception $e) {
            Logger::log('fatal', __METHOD__ . ': ' . $e->getMessage(), isset($e->data) ? $e->data : []);
        }

        //Check if it is a retry webhook and if process is allowed
        if (!Config::$settings['process_webhook_retries'] && $trx->isDuplicated())
            return Logger::log('debug', 'notification > webhook | Mobbex Webhook: Duplicated Request Detected');

        // Only process if it is a parent webhook
        if ($data['parent']) {
            $order ? $this->updateOrder($order, $data, $trx) : $this->createOrder($cart, $data, $trx);

            // Aditional webhook process
            \Mobbex\PS\Checkout\Models\Registrar::executeHook('actionMobbexWebhook', false, $postData['data'], $cartId);
        }

        die('OK: ' . \Mobbex\PS\Checkout\Models\Config::MODULE_VERSION);
    }

    /**
     * Update order data using a parent transaction.
     * 
     * @param \Order $order
     * @param array $data Formatted webhook data.
     * @param \MobbexTransaction $trx
     * 
     * @return mixed Update result.
     */
    public function updateOrder($order, $data, $trx)
    {
        $state = \Mobbex\PS\Checkout\Models\Transaction::getState($trx->status_code);

        // Exit if it is a failed operation and the order has already been paid
        if (in_array($state, ['expired', 'failed']) && $order->hasBeenPaid())
            return;

        // Notify if is updating an order created by other module
        if (!in_array($state, ['authorized', 'approved']) && !$order->module != 'mobbex')
            Logger::log('debug', 'notification > updateOrder | Updating an order created by other module', [
                'module'      => $order->module,
                'order'       => $order->id,
                'transaction' => $trx->id,
            ]);

        if ($data['source_name'] != 'Mobbex' && $data['source_name'] != $order->payment)
            $order->payment = $data['source_name'];

        // Update order status only if it was not updated recently
        if ($order->getCurrentState() == $data['order_status'])
            return $order->update();

        $this->orderUpdate->updateStock($order, $data['order_status']);
        $order->setCurrentState($data['order_status']);
        $this->orderUpdate->removeExpirationTasks($order);
        $this->orderUpdate->updateOrderPayment($order, $data);

        return $order->update();
    }

    /**
     * Create an order from a cart and transaction.
     * 
     * @param \Cart $cart
     * @param array $data Formatted webhook data.
     * @param \MobbexTransaction $trx
     */
    public function createOrder($cart, $data, $trx)
    {
        // Create only if payment was approved or holded
        if (!in_array(\Mobbex\PS\Checkout\Models\Transaction::getState($trx->status_code), ['approved', 'onhold']))
            return;

        // Exit if order first mode is enabled
        if (Config::$settings['order_first'])
            return Logger::log('fatal', 'notification > createOrder | [Order Creation Aborted] Trying to create order on webhook with order first mode', [
                'cart'        => $cart->id,
                'transaction' => $trx->id,
            ]);

        // Exit if cart was modified
        if (abs((float) $cart->getOrderTotal(true, \Cart::BOTH) - $data['checkout_total']) > 5) {
            $isFatal = Config::$settings['check_cart_totals'];

            Logger::log(
                $isFatal ? 'fatal' : 'error',
                'notification > createOrder | Difference found between cart and checkout totals ' + ($isFatal ? '[Order Creation Aborted]' : ''),
                [
                    'cart'          => $cart->id,
                    'transaction'   => $trx->id,
                    'cartTotal'     => (float) $cart->getOrderTotal(true, \Cart::BOTH),
                    'checkoutTotal' => $data['checkout_total'],
                ]
            );
        }

        // If finance charge discuount is enable, update cart total
        if (Config::$settings['charge_discount'])
            $cartRule = $this->orderUpdate->updateCartTotal($cart->id, $trx->total);

        // Create and validate Order
        $order = $this->module->helper->createOrder($cart->id, $data['order_status'], $trx->source_name, $this->module, false);

        if ($order)
            $this->orderUpdate->updateOrderPayment($order, $data);

        if (!empty($cartRule) && is_object($cartRule))
            $cartRule->delete();
    }
}
