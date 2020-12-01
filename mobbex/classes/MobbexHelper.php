<?php
/**
 * Mobbex.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 2.0.3
 * @see     PaymentModuleCore
 */

/**
 * Payment Provider Class
 */
class MobbexHelper
{
    const MOBBEX_VERSION = '2.0.3';

    const PS_16 = "1.6";
    const PS_17 = "1.7";

    const K_API_KEY = 'MOBBEX_API_KEY';
    const K_ACCESS_TOKEN = 'MOBBEX_ACCESS_TOKEN';
    const K_TEST_MODE = 'MOBBEX_TEST_MODE';

    // THEMES
    const K_THEME = 'MOBBEX_THEME';
    const K_THEME_BACKGROUND = 'MOBBEX_THEME_BACKGROUND';
    const K_THEME_PRIMARY = 'MOBBEX_THEME_PRIMARY';

    const K_THEME_SHOP_LOGO = 'MOBBEX_THEME_SHOP_LOGO';
    const K_THEME_LOGO = 'MOBBEX_THEME_LOGO';

    // RESELLER ID. Will change to Branch ID in the future
    const K_RESELLER_ID = 'MOBBEX_RESELLER_ID';

    const K_EMBED = 'MOBBEX_EMBED';
    const K_WALLET = 'MOBBEX_WALLET';

    const K_DEF_THEME = 'light';
    const K_DEF_BACKGROUND = '#ECF2F6';
    const K_DEF_PRIMARY = '#6f00ff';

    const K_THEME_LIGHT = 'light';
    const K_THEME_DARK = 'dark';

    const K_PLANS = 'MOBBEX_PLANS';
    const K_PLANS_TAX_ID = 'MOBBEX_PLANS_TAX_ID';
    const K_PLANS_TEXT = 'MOBBEX_PLANS_TEXT';
    const K_PLANS_TEXT_COLOR = 'MOBBEX_PLANS_TEXT_COLOR';
    const K_PLANS_BACKGROUND = 'MOBBEX_PLANS_BACKGROUND';

    const K_DEF_PLANS_TEXT = 'Planes Mobbex';
    const K_DEF_PLANS_TEXT_COLOR = '#ffffff';
    const K_DEF_PLANS_BACKGROUND = '#8900ff';

    const K_OWN_DNI = 'MOBBEX_OWN_DNI';
    const K_CUSTOM_DNI = 'MOBBEX_CUSTOM_DNI';

    const K_OS_PENDING = 'MOBBEX_OS_PENDING';
    const K_OS_WAITING = 'MOBBEX_OS_WAITING';
    const K_OS_REJECTED = 'MOBBEX_OS_REJECTED';

    public static function getUrl($path)
    {
        return Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . $path;
    }

    public static function getModuleUrl($controller, $action, $path)
    {
        // controller / module / fc
        // controller=notification
        // module=mobbex
        // fc=module
        return MobbexHelper::getUrl('index.php?controller=' . $controller . '&module=mobbex&fc=module&action=' . $action . $path);
    }

    public static function getWebhookUrl($params)
    {
        return Context::getContext()->link->getModuleLink(
            'mobbex',
            'webhook',
            $params,
            true
        );
    }

    public static function getPlatform()
    {
        return array(
            "name" => "prestashop",
            "version" => MobbexHelper::MOBBEX_VERSION,
            "platform_version" => _PS_VERSION_,
        );
    }

    public static function getHeaders()
    {
        return array(
            'cache-control: no-cache',
            'content-type: application/json',
            'x-access-token: ' . Configuration::get(MobbexHelper::K_ACCESS_TOKEN),
            'x-api-key: ' . Configuration::get(MobbexHelper::K_API_KEY),
        );
    }

    public static function getOptions()
    {
        $custom_logo = Configuration::get(MobbexHelper::K_THEME_LOGO);

        // If store's logo option is disabled, use the one configured in mobbex
        $default_logo = null;
        if (!empty(Configuration::get(MobbexHelper::K_THEME_SHOP_LOGO))) {
            $default_logo = Tools::getShopDomainSsl(true, true) . _PS_IMG_ .Configuration::get('PS_LOGO');
        }

        $theme_background = Configuration::get(MobbexHelper::K_THEME_BACKGROUND);
        $theme_primary = Configuration::get(MobbexHelper::K_THEME_PRIMARY);

        $theme = array(
            "type" => Configuration::get(MobbexHelper::K_THEME, MobbexHelper::K_DEF_THEME) ? 'light' : 'dark',
            "header" => [
                "name" => Configuration::get('PS_SHOP_NAME'),
                "logo" => !empty($custom_logo) ? $custom_logo : $default_logo,
            ],
            'background' => !empty($theme_background) ? $theme_background : null,
            'colors' => [
                'primary' => !empty($theme_primary) ? $theme_primary : null,
            ],
        );

        $options = array(
            'button' => (Configuration::get(MobbexHelper::K_EMBED) == true),
            'domain' => Context::getContext()->shop->domain,
            "theme" => $theme,
            // Will redirect automatically on Successful Payment Result
            "redirect" => [
                "success" => true,
                "failure" => false,
            ],
            "platform" => MobbexHelper::getPlatform(),
        );

        return $options;
    }

    public static function getReference($cart)
    {
        return 'ps_order_cart_' . $cart->id . '_time_'.time();
    }

    public static function createCheckout($module, $cart, $customer)
    {
        $curl = curl_init();

        // Get items
        $items = array();
        $products = $cart->getProducts(true);

        foreach ($products as $product) {
            //p($product);
            $image = Image::getCover($product['id_product']);
            $link = new Link; //because getImageLInk is not static function
            $imagePath = $link->getImageLink($product['link_rewrite'], $image['id_image'], 'home_default');

            $items[] = array("image" => 'https://' . $imagePath, "description" => $product['name'], "quantity" => $product['cart_quantity'], "total" => round($product['price_wt'], 2));
        }

        // Create data
        $data = array(
            'reference' => MobbexHelper::getReference($cart),
            'currency' => 'ARS',
            'description' => 'Carrito #' . $cart->id,
            'test' => (Configuration::get(MobbexHelper::K_TEST_MODE) == true),
            'return_url' => MobbexHelper::getModuleUrl('notification', 'return', '&id_cart=' . $cart->id . '&customer_id=' . $customer->id),
            'items' => $items,
            'installments' => MobbexHelper::getInstallments($products),
            'webhook' => MobbexHelper::getWebhookUrl(array(
                'id_cart' => $cart->id,
                'customer_id' => $customer->id,
                'key' => $customer->secure_key,
            )),
            'options' => MobbexHelper::getOptions(),
            'total' => (float) $cart->getOrderTotal(true, Cart::BOTH),
            'customer' => array(
                'name' => $customer->firstname . ' ' . $customer->lastname,
                'email' => $customer->email,
                'phone' => !empty($customer->phone) ? $customer->phone : null,
                'identification' => !empty(MobbexHelper::getDni($customer->id)) ? MobbexHelper::getDni($customer->id) : null,
                'uid' => !empty($customer->id) ? $customer->id : null,
            ),
            'timeout' => 5,
            'intent' => defined('MOBBEX_CHECKOUT_INTENT') ? MOBBEX_CHECKOUT_INTENT : null,
            'wallet' => (Configuration::get(MobbexHelper::K_WALLET) && Context::getContext()->customer->isLogged()),
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.mobbex.com/p/checkout",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => self::getHeaders(),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            d("cURL Error #:" . $err);
        } else {
            $res = json_decode($response, true);

            // Send return url to use later in js redirect
            $res['data']['return_url'] = $data['return_url'];

            return $res['data'];
        }
    }

    /**
     * Get the payment data
     *
     * @return array
     */
    public static function getPaymentData()
    {
        $cart = Context::getContext()->cart;
        $customer = Context::getContext()->customer;

        return MobbexHelper::createCheckout(null, $cart, $customer);
    }

    public static function evaluateTransactionData($res)
    {
        // Get the Status
        $status = (int) $res['payment']['status']['code'];

        // Get the Reference ( Transaction ID )
        $transaction_id = $res['payment']['id'];

        $source_type = $res['payment']['source']['type'];
        $source_name = $res['payment']['source']['name'];

        $message = $res['payment']['status']['message'];

        $total = (float) $res['payment']['total'];

        // Create Result Array
        $result = array(
            'status' => $status,
            'orderStatus' => (int) Configuration::get(MobbexHelper::K_OS_WAITING),
            'message' => $message,
            'name' => $source_name,
            'transaction_id' => $transaction_id,
            'source_type' => $source_type,
            'total' => $total,
            'data' => $res,
        );

        if ($status == 200) {
            $result['orderStatus'] = (int) Configuration::get('PS_OS_PAYMENT');
        } elseif ($status == 1 && $source_type != 'card') {
            $result['orderStatus'] = (int) Configuration::get(MobbexHelper::K_OS_PENDING);
        } elseif ($status == 2 || $status == 3) {
            $result['orderStatus'] = (int) Configuration::get(MobbexHelper::K_OS_WAITING);
        } else {
            $result['orderStatus'] = (int) Configuration::get(MobbexHelper::K_OS_REJECTED);
        }

        return $result;
    }

    public static function getDni($customer_id)
    {
        if (!$customer_id) {
            return false;
        }

        if (Configuration::get(MobbexHelper::K_CUSTOM_DNI) != '') {
            $dni_column = Configuration::get(MobbexHelper::K_CUSTOM_DNI);
        } elseif (Configuration::get(MobbexHelper::K_OWN_DNI)) {
            $dni_column = "billing_dni";
        } else {
            return false;
        }

        $table_columns = DB::getInstance()->executeS("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "customer` LIKE '" . $dni_column . "'");

        if (empty($table_columns)) {
            return false;
        }

        return DB::getInstance()->getRow(
            "SELECT `" . $dni_column . "` FROM `" . _DB_PREFIX_ . "customer` WHERE `id_customer` = " . $customer_id
        )[$dni_column];
    }

    public static function getPsVersion()
    {
        if (_PS_VERSION_ >= 1.7) {
            return self::PS_17;
        } else {
            return self::PS_16;
        }
    }

    public static function getInstallments($products)
    {

        $installments = [];

        $ahora = array(
            'ahora_3' => 'Ahora 3',
            'ahora_6' => 'Ahora 6',
            'ahora_12' => 'Ahora 12',
            'ahora_18' => 'Ahora 18',
        );

        foreach ($products as $product) {

            foreach ($ahora as $key => $value) {

                if (MobbexCustomFields::getCustomField($product['id_product'], 'product', $key)['data'] === 'yes') {
                    $installments[] = '-' . $key;
                    unset($ahora[$key]);
                }

            }

        }

        return $installments;

    }
}
