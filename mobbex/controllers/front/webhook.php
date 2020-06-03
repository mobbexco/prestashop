<?php
/**
 * webhook.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 1.0.0
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
        $transaction_id = "";

        // Un-Comment for Debugging
        // PrestaShopLogger::addLog('Card ID: ' . $cart_id);
        // PrestaShopLogger::addLog('Customer ID: ' . $customer_id);

        //Restore the context to process the order validation properly
        $context = Context::getContext();
        $context->cart = new Cart((int) $cart_id);
        $context->customer = new Customer((int) $customer_id);
        $context->currency = new Currency((int) $context->cart->id_currency);
        $context->language = new Language((int) $context->customer->id_lang);

        $order = new Order((int) Order::getOrderByCartId($cart_id));

        $secure_key = $context->customer->secure_key;
        $module_name = $this->module->displayName;
        $currency_id = (int) $context->currency->id;

        $res = array();
        parse_str(file_get_contents('php://input'), $res);

        $result = MobbexHelper::evaluateTransactionData($res['data']);

        // Get Transaction ID here
        $transaction_id = $result['transaction_id'];
        $status = (int) $result['status'];

        // Change the Order Total based on how much the client paid
        $amount = $result['total'];

        // Un-Comment for Debugging
        // PrestaShopLogger::addLog('Transaction ID: ' . $transaction_id);
        // PrestaShopLogger::addLog('Status ID: ' . $result['status']);

        // Only validate Status 2 or 200 nothing else
        // Status 2 => Waiting for Payment
        // Status 200 => Paid
        if ($status == 200 || $status == 2) {
            if (Validate::isLoadedObject($context->cart) && $context->cart->orderExists() == false) {
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
            }

            // Save the data
            MobbexTransaction::saveTransaction($cart_id, $result['data']);
        }

        echo "OK: " . MobbexHelper::MOBBEX_VERSION;

        die();
    }
}
