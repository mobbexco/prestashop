<?php

if (!defined('_PS_VERSION_'))
    exit;

/**
 * Class to avoid redirect issues when using OnePage Checkout in Safari.
 * It is used in case the browser validates that the url of the form action attribute belongs to the same host.
 */
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