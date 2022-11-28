<?php

defined('_PS_VERSION_') || exit;

class MobbexPaymentModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
        $this->config = new \Mobbex\PS\Checkout\Models\Config();
        $this->logger = new \Mobbex\PS\Checkout\Models\Logger();
    }
    
    public function postProcess()
    {
        // We don't do anything if the module has been disabled
        if ($this->module->active == false)
            $this->logger->log('fatal', 'payment > postProcess | Payment Controller Call On Module Inactive', $_REQUEST);

        if (Tools::getValue('action') == 'process')
            die(json_encode($this->process()));

        if (Tools::getValue('action') == 'redirect')
            Tools::redirect($this->getCheckoutUrl($this->process()));
    }

    /**
     * Create checkout|subscriber and process the order if needed.
     * 
     * @return array
     */
    public function process()
    {
        //Store cart id in session
        $context = Context::getContext();
        $context->cookie->__set('last_cart', $context->cart->id);

        return [
            'data'  => \Mobbex\PS\Checkout\Models\Helper::getPaymentData() ?: null,
            'order' => $this->config->settings['order_first'] ? \Mobbex\PS\Checkout\Models\Helper::processOrder($this->module) : true,
        ];
    }

    /**
     * Get checkout url from query params or payment processed data if possible.
     * 
     * @param array $paymentData
     * 
     * @return string
     */
    public function getCheckoutUrl($paymentData = [])
    {
        $previousCheckoutUrl = 'https://mobbex.com/p/checkout/v2/' . Tools::getValue('id');

        return (isset($paymentData['data']['url']) ? $paymentData['data']['url'] : $previousCheckoutUrl) . '?' . http_build_query([
            'paymentMethod' => Tools::getValue('method', null),
        ]);
    }
}