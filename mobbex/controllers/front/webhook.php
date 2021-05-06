<?php
/**
 * webhook.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 2.1.3
 * @see     PaymentModuleCore
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * This class receives a POST request from the PSP and creates the PrestaShop
 * order according to the request parameters
 **/
class MobbexWebhookModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        // Un-Comment for Debugging
        // PrestaShopLogger::addLog('Mobbeex incoming webhook: Execute');

        $this->executeWebhook();
    }

    /*
     * Handles the Instant Payment Notification
     *
     * @return bool
     */
    protected function executeWebhook()
    {
        // Get Data from request
        $cart_id = Tools::getValue('id_cart');
        $customer_id = Tools::getValue('customer_id');

        // Get POST data
        $res = [];
        parse_str(file_get_contents('php://input'), $res);

        // Restore the context to process the order validation properly
        $context = Context::getContext();
        $context->cart = new Cart((int) $cart_id);
        $context->customer = new Customer((int) $customer_id);
        $context->currency = new Currency((int) $context->cart->id_currency);
        $context->language = new Language((int) $context->customer->id_lang);

        $order = new Order((int) Order::getOrderByCartId($cart_id));
        $result = MobbexHelper::evaluateTransactionData($res['data']);

        $status = (int) $result['status'];
        if ($status == 2 || $status == 3 || $status == 100 || $status == 200) {
            if (Validate::isLoadedObject($context->cart)) {
                // If Order does not exist
                if (!$context->cart->orderExists()) {
                    // Validate Order
                    $validation_response = $this->createOrder($cart_id, $result, $context, $status);
                    if (!empty($validation_response)) {
                        // Save validation errors
                        $result['data']['validation_error'] = $validation_response;
                    }
                } elseif ($context->cart->orderExists() && $status == 200) {
                    // Update order status
                    $order->setCurrentState((int) Configuration::get('PS_OS_PAYMENT'));
                    $order->save();
                }
            }

            // Save the data
            MobbexTransaction::saveTransaction($cart_id, $result['data']);
        }

        echo "OK: " . MobbexHelper::MOBBEX_VERSION;
        die();
    }

    /**
     * Create order
     * 
     * @param $cart_id
     * @param $amount
     * @param $status
     * 
     * @return $order_id
     */
    protected function createOrder($cart_id, $result, $context, $status)
    {
        try {
            $amount = (float) $context->cart->getOrderTotal(true, Cart::BOTH);
            $transaction_id = $result['transaction_id'] ? : '';
            $secure_key = $context->customer->secure_key;
            $currency_id = (int) $context->currency->id;

            $this->module->validateOrder(
                $cart_id,
                $result['orderStatus'],
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
        } catch (Exception $e) {
            // Get order state
            if ($status >= 200) {
                $state_id = (int) Configuration::get('PS_OS_PAYMENT');
            } else {
                $state_id = (int) Configuration::get(MobbexHelper::K_OS_WAITING) ? : Configuration::get('PS_OS_COD_VALIDATION');
            }

            // Create order
            $this->module->validateOrder(
                $cart_id,
                $state_id,
                $amount,
                'Mobbex'
            );
            return 'Error creating Order on Webhook (Basic Order created): ' . $e->getMessage();
        } finally {
            return 'Error creating Basic Order on Webhook';
        }

        return;
    }
}