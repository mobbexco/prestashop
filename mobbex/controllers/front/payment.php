<?php

defined('_PS_VERSION_') || exit;

use Mobbex\PS\Checkout\Models\Config;
use Mobbex\PS\Checkout\Models\Logger;

class MobbexPaymentModuleFrontController extends ModuleFrontController
{   
    public function postProcess()
    {
        // We don't do anything if the module has been disabled
        if ($this->module->active == false)
            Logger::log('fatal', 'payment > postProcess | Payment Controller Call On Module Inactive', $_REQUEST);

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
            'data'  => $this->module->helper->getPaymentData() ?: null,
            'order' => Config::$settings['order_first'] ? $this->module->helper->processOrder($this->module) : true,
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
        return isset($paymentData['data']['url']) ? $paymentData['data']['url'] . '?' . http_build_query([
            'paymentMethod' => Tools::getValue('method', null),
        ]) : 'index.php?controller=order&step=1';
    }
}