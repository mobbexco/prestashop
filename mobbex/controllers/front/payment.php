<?php

defined('_PS_VERSION_') || exit;

class MobbexPaymentModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        // We don't do anything if the module has been disabled
        if ($this->module->active == false)
            MobbexHelper::log('Payment Controller Call On Module Inactive', $_REQUEST, true, true);

        if (Tools::getValue('action') == 'process')
            die(json_encode($this->process()));
    }

    /**
     * Create checkout|subscriber and process the order if needed.
     */
    public function process()
    {
        return [
            'order' => Configuration::get(MobbexHelper::K_ORDER_FIRST) ? MobbexHelper::processOrder($this->module) : true,
            'data'  => MobbexHelper::getPaymentData(),
        ];
    }
}