<?php
/**
 * webhook.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 2.2.2
 * @see     PaymentModuleCore
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * This class receives a POST request from the PSP and creates the PrestaShop
 * order according to the request parameters
 */
class MobbexWebhookModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        // Un-Comment for Debugging
        // PrestaShopLogger::addLog('Mobbeex incoming webhook: Execute');

        $this->executeWebhook();
    }

    /**
     * Handles the Instant Payment Notification.
     */
    protected function executeWebhook()
    {
        // Get data from request
        $cart_id     = Tools::getValue('id_cart');
        $customer_id = Tools::getValue('customer_id');
        $res         = [];
        parse_str(file_get_contents('php://input'), $res);

        if (empty($cart_id) || empty($res))
            die("WebHook Error: Empty cart_id or Mobbex json data. " . MobbexHelper::MOBBEX_VERSION);

        // Restore the context to process the order validation properly
        $context           = Context::getContext();
        $context->cart     = new Cart((int) $cart_id);
        $context->customer = new Customer((int) $customer_id);
        $context->currency = new Currency((int) $context->cart->id_currency);
        $context->language = new Language((int) $context->customer->id_lang);

        // Get evaluated transaction data
        $trans_data = MobbexHelper::evaluateTransactionData($res['data']);
        $status     = $trans_data['status'];

        if (Validate::isLoadedObject($context->cart)) {
            // Try to get Order
            $order = MobbexHelper::orderExists($cart_id) ? new Order(MobbexHelper::getOrderByCartId($cart_id)) : false;

            // If Order exists
            if ($order) {
                // If it was not updated recently
                if ($order->getCurrentState() != $trans_data['orderStatus']) {
                    // Update order status
                    $order->setCurrentState($trans_data['orderStatus']);
                    $order->save();
                }
            } else {
                // Create and validate Order
                $validation_response = $this->createOrder($cart_id, $trans_data, $context, $status);

                // Save validation errors
                foreach ($validation_response as $error)
                    $trans_data['data']['validation_error'][] = $error;
            }
        }

        // Save the data
        MobbexTransaction::saveTransaction($cart_id, $trans_data['data']);

        echo "OK: " . MobbexHelper::MOBBEX_VERSION; die();
    }

    /**
     * Create order
     * 
     * @param string|int $cart_id
     * @param array $trans_data
     * @param Context $context
     * @param int $status
     * 
     * @return array $errors
     */
    protected function createOrder($cart_id, $trans_data, $context, $status)
    {
        $errors = [];

        try {
            $amount         = (float) $context->cart->getOrderTotal(true, Cart::BOTH);
            $transaction_id = $trans_data['transaction_id'] ? : '';
            $secure_key     = $context->customer->secure_key;
            $currency_id    = (int) $context->currency->id;

            $this->module->validateOrder(
                $cart_id,
                $trans_data['orderStatus'],
                $amount,
                $trans_data['name'], // Add Card name and Installments if exist here
                $trans_data['message'],
                array(
                    '{transaction_id}' => $transaction_id,
                    '{message}' => $trans_data['message'],
                ), // Other data like Transaction ID
                $currency_id,
                false,
                $secure_key
            );
        } catch (\Exception $e) {
            $errors[] = 'Error creating Order on Webhook: ' . $e->getMessage();

            // Try create order with basic data
            $response = $this->createBasicOrder($cart_id, $amount, $status);

            // Add possible response errors
            $errors = array_merge($errors, $response);
        }

        return $errors;
    }

    /**
     * Create basic order
     * 
     * @param string|int $cart_id
     * @param float $amount
     * @param int $status
     * 
     * @return array $errors
     */
    protected function createBasicOrder($cart_id, $amount, $status)
    {
        $errors = [];

        try {
            // Get order status
            $state = MobbexHelper::getState($status);
            if ($state == 'approved') {
                $state_id = (int) Configuration::get('PS_OS_PAYMENT');
            } else if ($state == 'on-hold') {
                $state_id = (int) Configuration::get(MobbexHelper::K_OS_WAITING) ?: (int) Configuration::get(MobbexHelper::K_OS_PENDING);
            } else if ($state == 'cancelled'){
                $state_id = (int) Configuration::get('PS_OS_ERROR');
            } else if ($state == 'rejected') {
                $state_id = (int) Configuration::get(MobbexHelper::K_OS_REJECTED) ?: Configuration::get('PS_OS_ERROR');
            }

            // Create order
            $this->module->validateOrder(
                $cart_id,
                $state_id,
                $amount,
                'Mobbex'
            );
        } catch (\Exception $e) {
            $errors[] = 'Error creating Basic Order on Webhook: ' .  $e->getMessage();
        }

        return $errors;
    } 
}