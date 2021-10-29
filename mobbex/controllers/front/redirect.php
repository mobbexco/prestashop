<?php

if (!defined('_PS_VERSION_'))
    exit;

class MobbexRedirectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;
    public $display_column_right = false;

    public function postProcess()
    {
        Tools::redirect(urldecode(Tools::getValue('checkout_url')));
    }
}