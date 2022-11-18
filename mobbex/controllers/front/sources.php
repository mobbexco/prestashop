<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MobbexSourcesModuleFrontController extends ModuleFrontController
{

    public function __construct()
    {
        parent::__construct();
        $this->config = new \Mobbex\Config();
    }
    
    public function postProcess()
    {
        // We don't do anything if the module has been disabled by the merchant
        if ($this->module->active == false)
            MobbexHelper::log('Sources update on module inactive.', $_REQUEST, true, true);
        
        if(Tools::getValue('hash') !== md5($this->config->settings['api_key'] . '!' . $this->config->settings['access_token']))
            return;

        if(Tools::getValue('action') == 'update')
            MobbexHelper::updateMobbexSources();
        
    }

}