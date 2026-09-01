<?php

defined('_PS_VERSION_') || exit;

use Mobbex\PS\Checkout\Models\Logger;

 class MobbexCaptureModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $token   = Tools::getValue('token');
        $orderId = Tools::getValue('order_id');
        if (!$token || !$orderId) {
            Logger::log('error', 'Mobbex > capture | Missing token or order_id', ['token' => $token, 'order_id' => $orderId]);
            return;
        }

        // Return if token doesn't match. This prevents access to the capture from outside.
        if (!\Mobbex\Repository::validateToken($token, $orderId))
            return;

        // Try to make a capture request and redirect
        try {
            // Set request necessary data
            $cartId  = Cart::getCartIdByOrderId($orderId);
            $url     = urldecode(Tools::getValue('url'));
            $mbbxTrx = \Mobbex\PS\Checkout\Models\Transaction::getTransactions($cartId, true);

            // Capture request
            \Mobbex\Api::request([
                'method' => 'POST',
                'uri'    => 'operations/' . $mbbxTrx->payment_id . '/capture',
                'body'   => ['total' => $mbbxTrx->total],
            ]);

            // Redirect back to the (same-origin) admin order page
            $path  = parse_url($url, PHP_URL_PATH) ?: '/';
            $query = parse_url($url, PHP_URL_QUERY);

            header('Location: ' . Tools::getShopDomainSsl(true, true) . $path . ($query ? '?' . $query : ''));
            exit;
        } catch (\Exception $e) {
            Logger::log('error', 'Mobbex > capture | Error making capture', $e->getMessage());
        }
    }
}
 