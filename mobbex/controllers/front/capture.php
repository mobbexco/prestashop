<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

 class MobbexCaptureModuleFrontController extends ModuleFrontController
{      
    /** @var \Mobbex\PS\Checkout\Models\OrderHelper */
    public $helper;

    /** @var \Mobbex\PS\Checkout\Models\Config */
    public $config;
    
    public function __construct()
    {
        parent::__construct();
        $this->logger = new \Mobbex\PS\Checkout\Models\Logger();
        $this->helper = new \Mobbex\PS\Checkout\Models\OrderHelper();
        $this->config = new \Mobbex\PS\Checkout\Models\Config();
    }

    public function postProcess()
    {
        // Retun if hash not match
        if(Tools::getValue('hash') !== md5($this->config->settings['api_key'] . '!' . $this->config->settings['access_token']))
            return;

        else {
            // Try to make a capture request and redirect
            try {
                // Set request necessary data
                $cartId  = Cart::getCartIdByOrderId(Tools::getValue('order_id'));
                $url     = urldecode(Tools::getValue('url')); 
                $mbbxTrx = \Mobbex\PS\Checkout\Models\Transaction::getTransactions($cartId, true);

                // Capture request
                \Mobbex\Api::request([
                    'method' => 'POST',
                    'uri'    => 'operations/' . $mbbxTrx->payment_id . '/capture',
                    'body'   => ['total' => $mbbxTrx->total],
                ]);

                Tools::redirectAdmin( $url);

            } catch (\Exception $e) {
                $this->logger->log('error', 'Mobbex > capture | Error making capture', $e->getMessage());
            }
        }
    }
}
 