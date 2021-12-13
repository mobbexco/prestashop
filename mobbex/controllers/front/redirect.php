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
        // Fire order process here for those who don't use the embed script
        if (Configuration::get(MobbexHelper::K_ORDER_FIRST)) {
            $order = MobbexHelper::processOrder($this->module);

            if (!$order)
                return Tools::redirect('index.php?controller=order&step=3&typeReturn=failure');
        }

        Tools::redirect(urldecode(Tools::getValue('checkout_url')));
    }
}