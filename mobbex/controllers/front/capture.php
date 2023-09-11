<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

 class MobbexCaptureModuleFrontController extends ModuleFrontController
{      
    /** @var \Mobbex\PS\Checkout\Models\Logger */
    public $logger;

    /** @var \Mobbex\PS\Checkout\Models\Config */
    public $config;
    
    public function __construct()
    {
        parent::__construct();
        $this->logger = new \Mobbex\PS\Checkout\Models\Logger();
        $this->config = new \Mobbex\PS\Checkout\Models\Config();
    }

    public function postProcess()
    {
        // Retun if hash not match. This prevents access to the site from outside the capture
        if(Tools::getValue('hash') !== md5($this->config->settings['api_key'] . '!' . $this->config->settings['access_token']))
            return;

        // Try to make a capture request and redirect
        try {
            // Set request necessary data
            $cartId  = Cart::getCartIdByOrderId(Tools::getValue('order_id'));
            $url     = urldecode(Tools::getValue('url'));
            $mbbxTrx = \Mobbex\PS\Checkout\Models\Transaction::load($cartId);

            // Capture request
            \Mobbex\Api::request([
                'method' => 'POST',
                'uri'    => 'operations/' . $mbbxTrx->payment_id . '/capture',
                'body'   => ['total' => $mbbxTrx->total],
            ]);

            Tools::redirectAdmin($url);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Mobbex > capture | Error making capture', $e->getMessage());
        }
    }
}
 