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
            $this->module->logger::log(
                'fatal',
                'sources > postProcess | Sources update on module inactive.',
                $_REQUEST
            );

        if (!$this->module->config::validateHash(Tools::getValue('hash')))
            return;

        if (Tools::getValue('action') == 'update')
            $this->module->config::updateMobbexSources();
    }
}