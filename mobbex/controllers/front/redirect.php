<?php
/**
 * redirect.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 2.2.3
 * @see     PaymentModuleCore
 */

/*
 * This front controller builds the payment request and then redirects the
 * customer to the PSP website so that he can pay safely
 */
class MobbexRedirectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * Initialize common front page content
     *
     * @see    FrontControllerCore::initContent()
     * @return void
     */
    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;
        parent::initContent();
    }

    /**
     * Process request and create checkout URL
     */
    public function postProcess()
    {
        // Create URL and Redirect
        Tools::redirect(MobbexHelper::getPaymentData()['url']);
    }
}
