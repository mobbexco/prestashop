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

        if ($action == 'return') {
            return $this->return();
        } else if ($action == 'webhook') {
            return $this->webhook();
        } else {
            return Tools::redirect("index.php");
        }
    }

    public function return()
    {
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

    public function webhook()
    {
        // Get data from request
        $cartId = Tools::getValue('id_cart');
        $res    = [];
        parse_str(file_get_contents('php://input'), $res);

        if (empty($cartId) || empty($res))
            die('WebHook Error: Empty cart_id or Mobbex json data. ' . MobbexHelper::MOBBEX_VERSION);

        // Get Order and transaction data
        $order     = MobbexHelper::getOrderByCartId($cartId, true);
        $transData = MobbexHelper::evaluateTransactionData($res['data']);

        // If Order exists
        if ($order) {
            // If it was not updated recently
            if ($order->getCurrentState() != $transData['orderStatus']) {
                // Update order status
                $order->setCurrentState($transData['orderStatus']);
                $order->save();
            }
        } else {
            // Create and validate Order
            $this->createOrder($cartId, $transData);
        }

        // Save the data and return
        MobbexTransaction::saveTransaction($cartId, $transData['data']);
        die('OK: ' . MobbexHelper::MOBBEX_VERSION);
    }

    /**
     * Create an order from Cart.
     * 
     * @param string|int $cartId
     * @param array $transData
     * @param Context $context
     */
    protected function createOrder($cartId, $transData)
    {
        try {
            $context       = $this->restoreContext($cartId);
            $amount        = (float) $context->cart->getOrderTotal(true, Cart::BOTH);
            $transactionId = $transData['transaction_id'] ? : '';
            $secureKey     = $context->customer->secure_key;
            $currencyId    = (int) $context->currency->id;

            $this->module->validateOrder(
                $cartId,
                $transData['orderStatus'],
                $amount,
                $transData['name'], // Add Card name and Installments if exist here
                $transData['message'],
                [
                    '{transaction_id}' => $transactionId,
                    '{message}' => $transData['message'],
                ], // Other data like Transaction ID
                $currencyId,
                false,
                $secureKey
            );
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Error creating Order on Webhook: ' . $e->getMessage(), 3, null, 'Mobbex', $cartId, true, null);
        }
    }

    /**
     * Restore the context to process the order validation properly.
     * 
     * @param int|string $cartId 
     * 
     * @return Context $context 
     */
    protected function restoreContext($cartId)
    {
        $context           = Context::getContext();
        $context->cart     = new Cart((int) $cartId);
        $context->customer = new Customer((int) Tools::getValue('customer_id'));
        $context->currency = new Currency((int) $context->cart->id_currency);
        $context->language = new Language((int) $context->customer->id_lang);

        if (!Validate::isLoadedObject($context->cart))
            PrestaShopLogger::addLog('Error getting context on Webhook: ', 3, null, 'Mobbex', $cartId, true, null);

        return $context;
    }
}