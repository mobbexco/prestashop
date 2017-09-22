<?php
/**
 * Mobbex.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 1.0.0
 * @see     PaymentModuleCore
 */

/**
 * Payment Provider Class
 */
class MobbexHelper
{
    const K_API_KEY = 'MOBBEX_API_KEY';
    const K_ACCESS_TOKEN = 'MOBBEX_ACCESS_TOKEN';

    // Configuration::get('PS_OS_PAYMENT') => 2
    // Configuration::get('PS_OS_CANCELED') => 6
    // Configuration::get('PS_OS_ERROR') => 8
    const K_OS_PENDING = 'MOBBEX_OS_PENDING';
    const K_OS_WAITING = 'MOBBEX_OS_WAITING';
    const K_OS_REJECTED = 'MOBBEX_OS_REJECTED';

    public static function getUrl($path)
    {
        return Tools::getShopDomain(true, true).__PS_BASE_URI__.$path;
    }

    public static function getModuleUrl($controller, $action, $path)
    {
        // controller / module / fc
        // controller=notification
        // module=mobbex
        // fc=module
        return MobbexHelper::getUrl('index.php?controller='.$controller.'&module=mobbex&fc=module&action='.$action.$path);
    }

    public static function getHeaders()
    {
        return array(
            'cache-control: no-cache',
            'content-type: application/x-www-form-urlencoded',
            'x-access-token: '. Configuration::get('MOBBEX_ACCESS_TOKEN'),
            'x-api-key: '.Configuration::get('MOBBEX_API_KEY')
        );
    }

    public static function getReference($customer, $cart)
    {
        return 'ps_order_customer_'.$customer->id.'_cart_'.$cart->id.'_seed_'.mt_rand(100000, 999999);
    }

    public static function createCheckout($module, $cart, $customer)
    {
        $curl = curl_init();

        // Create an unique id
        $tracking_ref = MobbexHelper::getReference($customer, $cart);

        $items = array();
        $products = $cart->getProducts(true);

        //p($products);

        foreach($products as $product) {
            //p($product);
            $image = Image::getCover($product['id_product']);
            $link = new Link; //because getImageLInk is not static function
            $imagePath = $link->getImageLink($product['link_rewrite'], $image['id_image'], 'home_default');

            $items[] = array("image" => $imagePath, "description" => $product['name'], "quantity" => $product['cart_quantity'], "total" => round($product['price_wt'],2) );
        }

        // Create data
        $data = array(
            'reference' => $tracking_ref,
            'currency' => 'ARS',
            'email' => $customer->email,
            'description' => 'Orden #'.$cart->id,
            // notification / return => '&id_cart='.$cart->id.'&customer_id='.$customer->id
            'return_url' => MobbexHelper::getModuleUrl('notification', 'return', '&id_cart='.$cart->id.'&customer_id='.$customer->id),
            // notification / hook => '&id_cart='.$cart->id.'&customer_id='.$customer->id.'&key='.$customer->secure_key
            'items' => $items,
            'webhook' => MobbexHelper::getModuleUrl('notification', 'hook', '&id_cart='.$cart->id.'&customer_id='.$customer->id.'&key='.$customer->secure_key),
            'redirect' => 0,
            'total' => (float)$cart->getOrderTotal(true, Cart::BOTH),
        );

        //d($data);

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://mobbex.com/p/checkout/create",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => self::getHeaders(),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            d("cURL Error #:" . $err);
        } else {
            $res = json_decode($response, true);

            return $res['data']['url'];
        }
    }

    /**
     * Get the payment URL
     *
     * @return string
     */
    public static function getPaymentUrl()
    {
        $module = Context::getContext()->controller->module;
        $cart = Context::getContext()->cart;
        $customer = Context::getContext()->customer;

        return MobbexHelper::createCheckout($module, $cart, $customer);
    }

    public static function evaluateTransactionData($res)
    {
        // Get the Status
        $status = $res['payment']['status']['code'];

        // Get the Reference ( Transaction ID )
        $transaction_id = $res['payment']['reference'];

        $source_type = $res['payment']['source']['type'];
        $source_name = $res['payment']['source']['name'];

        $message = $res['payment']['status']['message'];

        // Create Result Array
        $result = array(
            'status' => Configuration::get(MobbexHelper::K_OS_WAITING),
            'message' => $message,
            'name' => $source_name,
            'data' => $res
        );

        if ($status == 200) {
            $result['status'] = Configuration::get('PS_OS_PAYMENT');
        } elseif ($status == 1 && $source_type != 'card') {
            $result['status'] = Configuration::get(MobbexHelper::K_OS_PENDING);
        } elseif ($status == 2 && $source_type != 'card') {
            $result['status'] = Configuration::get(MobbexHelper::K_OS_WAITING);
        } else {
            $result['status'] = Configuration::get(MobbexHelper::K_OS_REJECTED);
        }

        return $result;
    }

    public static function getTransaction($context, $transaction_id)
    {
        $curl = curl_init();

        // Create data
        $data = array(
            'id' => $transaction_id
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://mobbex.com/2.0/transactions/status",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => self::getHeaders(),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            $res = json_decode($response, true);

            return self::evaluateTransactionData($res['data']['transaction']);
        }
    }
}
