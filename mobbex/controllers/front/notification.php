<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MobbexNotificationModuleFrontController extends ModuleFrontController
{
    /** @var \Mobbex\PS\Checkout\Models\OrderUpdate */
    public $orderUpdate;

    /** @var \Mobbex\PS\Checkout\Models\Config */
    public $config;

    /** @var \Mobbex\PS\Checkout\Models\Logger */
    public $logger;

    public function __construct()
    {
        parent::__construct();
        $this->config = new \Mobbex\PS\Checkout\Models\Config();
        $this->logger = new \Mobbex\PS\Checkout\Models\Logger();
    }

    public function postProcess()
    {
        // We don't do anything if the module has been disabled by the merchant
        if ($this->module->active == false)
            $this->logger->log('fatal', 'notification > postProcess | Notification On Module Inactive', $_REQUEST);

        $this->orderUpdate = new \Mobbex\PS\Checkout\Models\OrderUpdate;

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
        $order_id = \Mobbex\PS\Checkout\Models\Helper::getOrderByCartId($cart_id);

        // If order was not created
        if (empty($order_id)) {
            $seconds = $this->config->settings['redirect_time'] ?: 10;

            // Wait for webhook
            while ($seconds > 0 && !$order_id) {
                sleep(1);
                $seconds--;
                $order_id = \Mobbex\PS\Checkout\Models\Helper::getOrderByCartId($cart_id);
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
            $order = \Mobbex\PS\Checkout\Models\Helper::getOrderByCartId($cart_id, true);

            if($order && $this->config->settings['order_first'] && $this->config->settings['cart_restore']){
                //update stock
                $this->orderUpdate->updateStock($order, Configuration::get('PS_OS_CANCELED'));
                //Cancel the order
                $order->setCurrentState(Configuration::get('PS_OS_CANCELED'));
                $order->update();
                //Restore the cart
                $cart = new Cart($cart_id);
                \Mobbex\PS\Checkout\Models\Helper::restoreCart($cart); 
            }

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
        $cartId   = Tools::getValue('id_cart');
        $postData = isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' ? json_decode(file_get_contents('php://input'), true) : $_POST;

        if (!$cartId || !isset($postData['data']))
            $this->logger->log('fatal', 'notification > webhook | Invalid Webhook Data', $_REQUEST);

        // Get cart and order
        $cart  = new \Cart($cartId);
        $order = \Mobbex\PS\Checkout\Models\Helper::getOrderByCartId($cartId, true);

        // Format data and save trx to db
        $data = \Mobbex\PS\Checkout\Models\Helper::getTransactionData($postData['data']);
        $trx  = \Mobbex\PS\Checkout\Models\Transaction::saveTransaction($cartId, $data);

        // Check if it is a retry webhook and if process is allowed
        if (!$this->config->settings['process_webhook_retries'] && $trx->isRetry())
            die('OK: ' . \Mobbex\PS\Checkout\Models\Config::MODULE_VERSION);

        // Only process if it is a parent webhook
        if ($data['parent']) {
            $order ? $this->updateOrder($order, $data, $trx) : $this->createOrder($cartId, $data, $trx);

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
        if ($data['source_name'] != 'Mobbex' && $data['source_name'] != $order->payment)
            $order->payment = $data['source_name'];

        // Update order status only if it was not updated recently
        if ($order->getCurrentState() != $data['order_status']) {
            $this->orderUpdate->updateStock($order, $data['order_status']);
            $order->setCurrentState($data['order_status']);
            $this->orderUpdate->removeExpirationTasks($order);
            $this->orderUpdate->updateOrderPayment($order, $data);
        }

        $order->update();
    }

    /**
     * Create an order from a cart and transaction.
     * 
     * @param int $cartId
     * @param array $data Formatted webhook data.
     * @param \MobbexTransaction $trx
     */
    public function createOrder($cartId, $data, $trx)
    {
        // If finance charge discuount is enable, update cart total
        if ($this->config->settings['charge_discount'])
            $cartRule = $this->orderUpdate->updateCartTotal($cartId, $data['total']);

        // Create and validate Order
        $order = \Mobbex\PS\Checkout\Models\Helper::createOrder($cartId, $data['order_status'], $data['source_name'], $this->module);

        if ($order)
            $this->orderUpdate->updateOrderPayment($order, $data);

        if (!empty($cartRule) && is_object($cartRule))
            $cartRule->delete();
    }
}