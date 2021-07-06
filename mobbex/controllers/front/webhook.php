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
    /**
     * Handles the Instant Payment Notification.
     */
    public function initContent()
    {
        // Get data from request
        $cartId = Tools::getValue('id_cart');
        $res    = [];
        parse_str(file_get_contents('php://input'), $res);

        if (empty($cartId) || empty($res))
            die('WebHook Error: Empty cart_id or Mobbex json data. ' . MobbexHelper::MOBBEX_VERSION);

        // Get Order and transaction data
        $order     = MobbexHelper::getOrderByCartId($cartId);
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

        if (!Validate::isLoadedObject($context->cart)) {
            PrestaShopLogger::addLog('Error getting context on Webhook: ', 3, null, 'Mobbex', $cartId, true, null);
        }

        return $context;
    }
}