<?php
/**
 * Mobbex.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 2.3.1
 * @see     PaymentModuleCore
 */

/**
 * Payment Provider Class
 */
class MobbexHelper
{
    const MOBBEX_VERSION = '2.3.1';

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
    const K_PLANS_TEXT = 'MOBBEX_PLANS_TEXT';
    const K_PLANS_TEXT_COLOR = 'MOBBEX_PLANS_TEXT_COLOR';
    const K_PLANS_BACKGROUND = 'MOBBEX_PLANS_BACKGROUND';
    const K_PLANS_IMAGE_URL = 'MOBBEX_PLANS_IMAGE_URL';
    const K_MULTICARD = 'MOBBEX_MULTICARD';
    
    const K_DEF_PLANS_TEXT = 'Planes Mobbex';
    const K_DEF_PLANS_TEXT_COLOR = '#ffffff';
    const K_DEF_PLANS_BACKGROUND = '#8900ff';
    const K_DEF_PLANS_IMAGE_URL = 'https://res.mobbex.com/images/sources/mobbex.png';
    const K_DEF_MULTICARD = false;

    const K_OWN_DNI = 'MOBBEX_OWN_DNI';
    const K_CUSTOM_DNI = 'MOBBEX_CUSTOM_DNI';

    const K_OS_PENDING = 'MOBBEX_OS_PENDING';
    const K_OS_WAITING = 'MOBBEX_OS_WAITING';
    const K_OS_REJECTED = 'MOBBEX_OS_REJECTED';

    static $transactionData = [];

    public static function getUrl($path)
    {
        return Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . $path;
    }

    public static function getModuleUrl($controller, $action, $path)
    {
        return MobbexHelper::getUrl('index.php?controller=' . $controller . '&module=mobbex&fc=module&action=' . $action . $path);
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

            $image = Image::getCover($product['id_product']);

            $prd = new Product($product['id_product']);
            if ($prd->hasAttributes()) {
                $images = $prd->getCombinationImages(Context::getContext()->language->id);
                $image = $images[$product['id_product_attribute']][0];
            }

            $link = new Link;
            $imagePath = $link->getImageLink($product['link_rewrite'], $image['id_image'], 'home_default');

            $items[] = [
                "image" => 'https://' . $imagePath, 
                "description" => $product['name'], 
                "quantity" => $product['cart_quantity'], 
                "total" => round($product['price_wt'], 2)
            ];
        }

        // Create data
        $data = array(
            'reference' => MobbexHelper::getReference($cart),
            'currency' => 'ARS',
            'description' => 'Carrito #' . $cart->id,
            'test' => (Configuration::get(MobbexHelper::K_TEST_MODE) == true),
            'return_url' => MobbexHelper::getModuleUrl('notification', 'return', '&id_cart=' . $cart->id . '&customer_id=' . $customer->id),
            'webhook' => MobbexHelper::getModuleUrl('notification', 'webhook', '&id_cart=' . $cart->id . '&customer_id=' . $customer->id),
            'items' => $items,
            'installments' => MobbexHelper::getInstallments($products),
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
            'multicard' => (Configuration::get(MobbexHelper::K_MULTICARD) == true),
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
            'orderStatus' => (int) Configuration::get(MobbexHelper::K_OS_PENDING),
            'message' => $message,
            'name' => $source_name,
            'transaction_id' => $transaction_id,
            'source_type' => $source_type,
            'total' => $total,
            'data' => $res,
        );

        // Validate mobbex status and create order status
        $state = self::getState($status);

        if ($state == 'onhold') {
            $result['orderStatus'] = (int) Configuration::get(MobbexHelper::K_OS_WAITING);
        } else if ($state == 'approved') {
            $result['orderStatus'] = (int) Configuration::get('PS_OS_PAYMENT');
        } else if ($state == 'failed') {
            $result['orderStatus'] = (int) Configuration::get('PS_OS_ERROR');
        } else if ($state == 'refunded') {
            $result['orderStatus'] = (int) Configuration::get('PS_OS_REFUND');
        } else if ($state == 'rejected') {
            $result['orderStatus'] = (int) Configuration::get(MobbexHelper::K_OS_REJECTED) ?: Configuration::get('PS_OS_ERROR');
        }

        self::$transactionData = $result;
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

    /**
     * Return the plans that were not selected in the product and category page
     */
    public static function getInstallments($products)
    {

        $installments = [];
        $total_advanced_plans = [];
        
        $ahora = array(
            'ahora_3' => 'Ahora 3',
            'ahora_6' => 'Ahora 6',
            'ahora_12' => 'Ahora 12',
            'ahora_18' => 'Ahora 18',
        );

        foreach ($products as $product) {
            $checkedCommonPlans = json_decode(MobbexCustomFields::getCustomField($product['id_product'], 'product', 'common_plans'));
            $checkedAdvancedPlans = json_decode(MobbexCustomFields::getCustomField($product['id_product'], 'product', 'advanced_plans'));

            if (!empty($checkedCommonPlans)) {
                foreach ($checkedCommonPlans as $key => $commonPlan) {
                    $installments[] = '-' . $commonPlan;
                    unset($checkedCommonPlans[$key]);
                }
            }

            if (!empty($checkedAdvancedPlans)) {
                $total_advanced_plans = array_merge($total_advanced_plans, $checkedAdvancedPlans);
            }
        }

        // Get all the advanced plans with their number of reps
        $counted_advanced_plans = array_count_values($total_advanced_plans);

        // Advanced plans
        foreach ($counted_advanced_plans as $plan => $reps) {
            // Only if the plan is active on all products
            if ($reps == count($products)) {
                // Add to installments
                $installments[] = '+uid:' . $plan;
            }
        }

        // Check "Ahora" custom fields
        $categoriesId = array();
        $categoriesId = self::getCategoriesId($products);
        foreach ($ahora as $key => $value) {
            //for each key, if it was not added before, then search all categories.
            if (!in_array('-' . $key, $installments)){
                foreach($categoriesId as $cat_id){
                    if (MobbexCustomFields::getCustomField($cat_id, 'category', $key) === 'yes') {
                        $installments[] = '-' . $key;
                        unset($ahora[$key]);
                        break;
                    }
                }
            }
        }

        return $installments;
    }

    /**
     * Return an array with categories ids
     * 
     * @param $listProducts : array
     * 
     * @return array
     */
    private function getCategoriesId($listProducts){
        
        $categories_id = array();
        
		foreach ($listProducts as $product) {
            $categories = array();
			$categories = Product::getProductCategoriesFull($product['id_product']);
			foreach ($categories as $category) {
				if(!in_array($category['id_category'], $categories_id)){
					array_push($categories_id,$category['id_category']);
				}
			}
		}
		return $categories_id;
    }

    /**
     * Get sources with common plans from mobbex.
     * 
     * @param integer|null $total
     * 
     * @return array
     */
    public static function getSources($total = null)
    {
        $curl = curl_init();

        $data = $total ? '?total=' . $total : null;

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.mobbex.com/p/sources' . $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => self::getHeaders(),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            d("cURL Error #:" . $err);
        } else {
            $response = json_decode($response, true);
            $data = $response['data'];

            if ($data) {
                return $data;
            }
        }

        return [];
    }

    /**
     * Get sources with advanced rule plans from mobbex.
     * 
     * @param string $rule
     * 
     * @return array
     */
    public static function getSourcesAdvanced($rule = 'externalMatch')
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => str_replace('{rule}', $rule, 'https://api.mobbex.com/p/sources/rules/{rule}/installments'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => self::getHeaders(),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            d("cURL Error #:" . $err);
        } else {
            $response = json_decode($response, true);
            $data = $response['data'];

            if ($data) {
                return $data;
            }
        }

        return [];
    }

    /**
    * Inform to Mobbex a total order refund 
    *
    * @return array
    */
    public static function porcessRefund($id_transaction)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.mobbex.com/p/operations/".$id_transaction."/refund",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => self::getHeaders(),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return d("CURL Error #:" . $err);
        } else {
            $res = json_decode($response, true);

            return $res['result'];
        }
    }

    /**
     * Return payment data from a cart, this additional information is for the invoice pdf
     * 
     * @param int $id_cart
     * @return String
     */
    public static function getInvoiceData($id_cart)
    {
        $transactionData = MobbexTransaction::getTransaction($id_cart);

        // Check if data exists
        if (empty($transactionData) || !is_array($transactionData)) {
            return false;
        }

        $cardNumber   = !empty($transactionData['payment']['source']['number']) ? $transactionData['payment']['source']['number'] : false;
        $habienteName = !empty($transactionData['entity']['name']) ? $transactionData['entity']['name'] : false;
        $idHabiente   = !empty($transactionData['customer']['identification']) ? $transactionData['customer']['identification'] : false;

        $tab = '<table style="border: solid 1pt black; padding:0 10pt">';
        // Card number
        if ($cardNumber) {
            $tab .= '<tr><td><b>Número de Tarjeta: </b></td><td>'.$cardNumber.'</td></tr>
            <tr><td></td><td></td></tr>';
        }

        // Customer name
        if ($habienteName) {
            $tab .= '<tr><td><b>Nombre de Tarjeta-Habiente: </b></td><td>'.$habienteName.'</td></tr>
            <tr><td></td><td></td></tr>';
        }

        // Customer ID
        if(!empty($idHabiente)){
            $tab .= '<tr><td><b>ID Tarjeta-habiente: </b></td><td>'.$idHabiente.'</td></tr>
            <tr><td></td><td></td></tr>';
        }

        $tab .= '</table>';
        return $tab;
    }

    /**
	 * Get payment state from Mobbex status code.
     * 
     * @param int|string $status
     * 
     * @return string "onhold" | "approved" | "refunded" | "rejected" | "failed"
	 */
    public static function getState($status)
    {
        if ($status == 2 || $status == 3 || $status == 100 || $status == 201) {
            return 'onhold';
        } else if ($status == 4 || $status >= 200 && $status < 400) {
            return 'approved';
        } else if ($status == 602 || $status == 605) {
            return 'refunded';
        } else if ($status == 604) {
            return 'rejected';
        } else {
            return 'failed';
        }
	}

    /**
     * Get Tax Id from Mobbex using API.
     * 
     * @return string $tax_id 
     */
    public static function getTaxId()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.mobbex.com/p/entity/validate",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => self::getHeaders(),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return d("CURL Error #:" . $err);
        } else {
            $res = json_decode($response, true);

            return $res['data']['tax_id'];
        }
    }

    /**
     * Get Order by Cart ID.
     * This method avoid fetch data from cache.
     * 
     * @param int|string $cart_id
     * @param bool $instance To return an instance of the order
     * 
     * @return Order|string|bool
     */
    public static function getOrderByCartId($cart_id, $instance = false)
    {
        $order_id = (int) Db::getInstance()->getValue(
            'SELECT `id_order`
            FROM `' . _DB_PREFIX_ . 'orders`
            WHERE `id_cart` = ' . (int) $cart_id .
            Shop::addSqlRestriction(), false
        );

        // Exit if it does not exist in the database
        if (empty($order_id))
            return false;

        return $instance ? new Order($order_id) : $order_id;
    }

    /**
     * Add data to javascript Mobbex variable.
     * 
     * @param array $vars 
     * 
     * @return void 
     */
    public static function addJavascriptData($vars)
    {
        ?>
        <script type='text/javascript'>
            var mbbx = {...mbbx, ...<?= json_encode($vars) ?>}
        </script>
        <?php
    }
}
