<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MobbexNotificationModuleFrontController extends ModuleFrontController
{
    /** @var \Mobbex\OrderUpdate */
    public $orderUpdate;

    /** @var \Mobbex\Config */
    public $config;

    /** @var \Mobbex\Logger */
    public $logger;

    public function __construct()
    {
        parent::__construct();
        $this->config = new \Mobbex\Config();
        $this->logger = new \Mobbex\Logger();
    }

    public function postProcess()
    {
        // We don't do anything if the module has been disabled by the merchant
        if ($this->module->active == false)
            $this->logger->log('fatal', 'notification > postProcess | Notification On Module Inactive', $_REQUEST);

        $this->orderUpdate = new \Mobbex\OrderUpdate;

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
            $seconds = $this->config->settings['redirect_time'] ?: 10;

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
            $order = MobbexHelper::getOrderByCartId($cart_id, true);

            if($order && $this->config->settings['order_first'] && $this->config->settings['cart_restore']){
                //update stock
                $this->updateStock($order, Configuration::get('PS_OS_CANCELED'));
                //Cancel the order
                $order->setCurrentState(Configuration::get('PS_OS_CANCELED'));
                $order->update();
                //Restore the cart
                $cart = new Cart($cart_id);
                MobbexHelper::restoreCart($cart); 
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

        // Get Order and transaction data
        $order = MobbexHelper::getOrderByCartId($cartId, true);
        $data = MobbexHelper::getTransactionData($postData['data']);
        
        // Save webhook data
        MobbexTransaction::saveTransaction($cartId, $data);

        // Only parent webhook can modify the order
        if ($data['parent']) {
            // Aditional webhook process
            \Mobbex\Registrar::executeHook('actionMobbexWebhook', false, $data, $cartId);

            // If Order exists
            if ($order) {
                if ($data['source_name'] != 'Mobbex' && $data['source_name'] != $order->payment)
                    $order->payment = $data['source_name'];

                // Update order status only if it was not updated recently
                if ($order->getCurrentState() != $data['order_status']) {
                    $this->updateStock($order, $data['order_status']);
                    $order->setCurrentState($data['order_status']);
                    $this->orderUpdate->removeExpirationTasks($order);
                    $this->orderUpdate->updateOrderPayment($order, $data);
                }

                $order->update();
                
            } else {
                // Create and validate Order
                $order = MobbexHelper::createOrder($cartId, $data['order_status'], $data['source_name'], $this->module);

                if ($order)
                    $this->orderUpdate->updateOrderPayment($order, $data);
            }
        }

        die('OK: ' . \Mobbex\Config::MODULE_VERSION);
        
    }

    /**
     * Update the stock of the product by discounting or refunding as the case may be.
     * @param Order $order
     * @param string $status
     */
    public function updateStock($order, $status)
    {
        $refund_status = [
            Configuration::get('PS_OS_ERROR'), 
            Configuration::get('PS_OS_CANCELLED'), 
            Configuration::get('MOBBEX_OS_REJECTED'), 
            Configuration::get('MOBBEX_OS_WAITING'), 
            Configuration::get('MOBBEX_OS_PENDING')
        ];

        if(in_array($order->getCurrentState(), $refund_status) || $status === Configuration::get('PS_OS_CANCELLED') || $status === Configuration::get('PS_OS_ERROR'))
            MobbexCustomFields::saveCustomField($order->id, 'order', 'refunded', 'yes');

        if($order->getCurrentState() === $this->config->orderStatuses['mobbex_status_pending']['name'] && !$this->config->setttings['pending_discount']){
            foreach ($order->getProductsDetail() as $product) {
                if(!StockAvailable::dependsOnStock($product['product_id']))
                    StockAvailable::updateQuantity($product['product_id'], $product['product_attribute_id'], -(int) $product['product_quantity'], $order->id_shop);
            }
        }

        if(MobbexCustomFields::getCustomField($order->id, 'order', 'refunded') !== 'yes' && !in_array($order->getCurrentState(), $refund_status)){
            if($status === Configuration::get('MOBBEX_OS_REJECTED') || $status === Configuration::get('MOBBEX_OS_WAITING')){
                foreach ($order->getProductsDetail() as $product) {
                    if(!StockAvailable::dependsOnStock($product['product_id']))
                        StockAvailable::updateQuantity($product['product_id'], $product['product_attribute_id'], (int) $product['product_quantity'], $order->id_shop);
                }
                MobbexCustomFields::saveCustomField($order->id, 'order', 'refunded', 'yes');
            }
        }
    }

}