<?php

defined('_PS_VERSION_') || exit;

use Mobbex\PS\Checkout\Models\Config;
use Mobbex\PS\Checkout\Models\Logger;

class MobbexSourcesModuleFrontController extends ModuleFrontController
{   
    public function postProcess()
    {
        // We don't do anything if the module has been disabled by the merchant
        if ($this->module->active == false)
            Logger::log(
                'fatal',
                'sources > postProcess | Sources update on module inactive.',
                $_REQUEST
            );

        if (!Config::validateHash(Tools::getValue('hash')))
            return;

        if (Tools::getValue('action') == 'update')
            Config::updateMobbexSources();
    }
}