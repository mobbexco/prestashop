<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

 class MobbexCaptureModuleFrontController extends ModuleFrontController
{      
    /** @var \Mobbex\PS\Checkout\Models\OrderHelper */
    public $helper;
    
    public function __construct()
    {
        parent::__construct();
        $this->logger = new \Mobbex\PS\Checkout\Models\Logger();
        $this->helper = new \Mobbex\PS\Checkout\Models\OrderHelper();
    }

    public function postProcess()
    {
        if(Tools::getValue('hash') !== md5($this->config->settings['api_key'] . '!' . $this->config->settings['access_token']))
            try {
                $cartId  = Cart::getCartIdByOrderId(Tools::getValue('order_id'));
                $url     = urldecode(Tools::getValue('url')); 
                $mbbxTrx = \Mobbex\PS\Checkout\Models\Transaction::getTransactions($cartId, true);

                // Make capture request
                $request = \Mobbex\Api::request([
                    'method' => 'POST',
                    'uri'    => 'operations/' . $mbbxTrx->payment_id . '/capture',
                    'body'   => ['total' => $mbbxTrx->total],
                ]);
                if (!$request)
                {
                    
                }
                Tools::redirectAdmin( $url);
            } catch (\Exception $e) {
                $this->logger->log('error', 'Mobbex > capture | Error making capture', $e->getMessage());
            }
    }
}
 