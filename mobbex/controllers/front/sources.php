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
        $this->logger = new \Mobbex\Logger();
    }
    
    public function postProcess()
    {
        // We don't do anything if the module has been disabled by the merchant
        if ($this->module->active == false)
            $this->logger->log('fatal', 'sources > postProcess | Sources update on module inactive.', $_REQUEST);
        
        if(Tools::getValue('hash') !== md5($this->config->settings['api_key'] . '!' . $this->config->settings['access_token']))
            return;

        if(Tools::getValue('action') == 'update')
            MobbexHelper::updateMobbexSources();
        
    }

}