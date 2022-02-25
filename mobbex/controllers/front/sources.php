<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MobbexSourcesModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        // We don't do anything if the module has been disabled by the merchant
        if ($this->module->active == false)
            MobbexHelper::log('Notification On Module Inactive', $_REQUEST, true, true);
        
        if(Tools::getValue('hash') !== md5(\Configuration::get(MobbexHelper::K_API_KEY) . '!' . \Configuration::get(MobbexHelper::K_ACCESS_TOKEN)))
            return;

        if(Tools::getValue('action') == 'update')
            MobbexHelper::updateMobbexSources();
        
    }

}