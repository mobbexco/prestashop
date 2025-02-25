<?php

defined('_PS_VERSION_') || exit;

use Mobbex\PS\Checkout\Models\Config;
use Mobbex\PS\Checkout\Models\Logger;

 class MobbexCaptureModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        // Retun if hash not match. This prevents access to the site from outside the capture
        if (!Config::validateHash(Tools::getValue('hash')))
            return;

        // Try to make a capture request and redirect
        try {
            // Set request necessary data
            $cartId  = Cart::getCartIdByOrderId(Tools::getValue('order_id'));
            $url     = urldecode(Tools::getValue('url'));
            $mbbxTrx = \Mobbex\PS\Checkout\Models\Transaction::getTransactions($cartId, true);

            // Capture request
            \Mobbex\Api::request([
                'method' => 'POST',
                'uri'    => 'operations/' . $mbbxTrx->payment_id . '/capture',
                'body'   => ['total' => $mbbxTrx->total],
            ]);

            Tools::redirectAdmin($url);
        } catch (\Exception $e) {
            Logger::log('error', 'Mobbex > capture | Error making capture', $e->getMessage());
        }
    }
}
 