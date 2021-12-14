<?php

defined('_PS_VERSION_') || exit;

class MobbexOrderModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        // We don't do anything if the module has been disabled
        if ($this->module->active == false)
            MobbexHelper::log('Order Controller Call On Module Inactive', $_REQUEST, true, true);

        if (Tools::getValue('action') == 'process' && Configuration::get(MobbexHelper::K_ORDER_FIRST))
            die(json_encode([
                'result'   => MobbexHelper::processOrder($this->module),
                'redirect' => MobbexHelper::getUrl('index.php?controller=order&step=3&typeReturn=failure')
            ]));
    }
}