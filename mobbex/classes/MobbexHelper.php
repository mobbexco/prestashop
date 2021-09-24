<?php

/**
 * Mobbex.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 2.4.1
 * @see     PaymentModuleCore
 */

/**
 * Payment Provider Class
 */
class MobbexHelper
{
    const MOBBEX_VERSION = '2.4.1';

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
    const K_PLANS_PADDING = 'MOBBEX_PLANS_PADDING';
    const K_PLANS_FONT_SIZE = 'MOBBEX_PLANS_FONT_SIZE';
    const K_PLANS_THEME = 'MOBBEX_PLANS_THEME';
    const K_MULTICARD = 'MOBBEX_MULTICARD';
    const K_UNIFIED_METHOD = 'MOBBEX_UNIFIED_METHOD';

    const K_DEF_PLANS_TEXT = 'Planes Mobbex';
    const K_DEF_PLANS_TEXT_COLOR = '#ffffff';
    const K_DEF_PLANS_BACKGROUND = '#8900ff';
    const K_DEF_PLANS_IMAGE_URL = 'https://res.mobbex.com/images/sources/mobbex.png';
    const K_DEF_PLANS_PADDING = '4px 18px';
    const K_DEF_PLANS_FONT_SIZE = '16px';
    const K_DEF_PLANS_THEME = MobbexHelper::K_THEME_LIGHT;
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
            $default_logo = Tools::getShopDomainSsl(true, true) . _PS_IMG_ . Configuration::get('PS_LOGO');
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
        return 'ps_order_cart_' . $cart->id . '_time_' . time();
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
            'customer' => self::getCustomer($cart),
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

    /**
     * Get customer data formatted for checkout.
     * 
     * @param Cart $cart
     *
     * @return array
     */
    public static function getCustomer($cart)
    {
        // Get address info from cart
        $address = new Address($cart->id_address_delivery);

        return [
            'name'           => $address->firstname . ' ' . $address->lastname,
            'email'          => Context::getContext()->customer->email,
            'phone'          => $address->phone_mobile ?: $address->phone,
            'identification' => $address->id_customer ? MobbexHelper::getDni($address->id_customer) : null,
            'uid'            => $address->id_customer,
        ];
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
        $dniColumn = trim(Configuration::get(MobbexHelper::K_CUSTOM_DNI)) ?: 'billing_dni';

        // Check if dni column exists
        if (empty(DB::getInstance()->executeS("SHOW COLUMNS FROM " . _DB_PREFIX_ . "customer LIKE '$dniColumn'")))
            return;

        return DB::getInstance()->getValue("SELECT $dniColumn FROM " . _DB_PREFIX_ . "customer WHERE id_customer = $customer_id");
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
     * Retrieve installments checked on plans filter of each product.
     * 
     * @param array $products
     * 
     * @return array
     */
    public static function getInstallments($products)
    {
        $installments = $inactivePlans = $activePlans = [];

        // Get plans from order products
        foreach ($products as $product) {
            $inactivePlans = array_merge($inactivePlans, MobbexHelper::getInactivePlans($product['id_product']));
            $activePlans   = array_merge($activePlans, MobbexHelper::getActivePlans($product['id_product']));
        }

        // Add inactive (common) plans to installments
        foreach ($inactivePlans as $plan)
            $installments[] = '-' . $plan;

        // Add active (advanced) plans to installments only if the plan is active on all products
        foreach (array_count_values($activePlans) as $plan => $reps) {
            if ($reps == count($products))
                $installments[] = '+uid:' . $plan;
        }

        // Remove duplicated plans and return
        return array_values(array_unique($installments));
    }

    /**
     * Return an array with categories ids
     * 
     * @param $listProducts : array
     * 
     * @return array
     */
    private function getCategoriesId($listProducts)
    {

        $categories_id = array();

        foreach ($listProducts as $product) {
            $categories = array();
            $categories = Product::getProductCategoriesFull($product['id_product']);
            foreach ($categories as $category) {
                if (!in_array($category['id_category'], $categories_id)) {
                    array_push($categories_id, $category['id_category']);
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
    public static function getSources($total = null, $inactivePlans = null)
    {
        $curl = curl_init();

        $data = $total ? '?total=' . $total : null;

        if ($data && $inactivePlans) {
            $data .= '&';
            foreach ($inactivePlans as $plan) {
                $data .= '&installments[]=-' . $plan;
            }
        }

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
     * Retrieve active advanced plans from a product and its categories.
     * 
     * @param int $productId
     * 
     * @return array
     */
    public static function getInactivePlans($productId = null)
    {
        // Get id from request if it is not set
        if (!$productId)
            $productId = Tools::getValue('id_product');

        $product = new Product($productId);

        $inactivePlans = json_decode(MobbexCustomFields::getCustomField($productId, 'product', 'common_plans')) ?: [];

        foreach ($product->getCategories() as $categoryId)
            $inactivePlans = array_merge($inactivePlans, json_decode(MobbexCustomFields::getCustomField($categoryId, 'category', 'common_plans')) ?: []);

        // Remove duplicated and return
        return array_unique($inactivePlans);
    }

    /**
     * Retrieve active advanced plans from a product and its categories.
     * 
     * @param int $productId
     * 
     * @return array
     */
    public static function getActivePlans($productId = null)
    {
        // Get id from request if it is not set
        if (!$productId)
            $productId = Tools::getValue('id_product');

        $product = new Product($productId);

        // Get plans from product and product categories
        $activePlans = json_decode(MobbexCustomFields::getCustomField($productId, 'product', 'advanced_plans')) ?: [];

        foreach ($product->getCategories() as $categoryId)
            $activePlans = array_merge($activePlans, json_decode(MobbexCustomFields::getCustomField($categoryId, 'category', 'advanced_plans')) ?: []);

        // Remove duplicated and return
        return array_unique($activePlans);
    }

    /**
     * Filter advanced sources 
     *
     * @return array
     */
    public static function filterAdvancedSources($sources, $advancedPlans)
    {
        foreach ($sources as $firstKey => $source) {
            foreach ($source['installments'] as $key => $installment) {
                if (!in_array($installment['uid'], $advancedPlans)) {
                    unset($sources[$firstKey]['installments'][$key]);
                }
            }
        }
        return $sources;
    }

    /**
     * Merge common sources with sources obtained by advanced rules.
     * 
     * @param mixed $sources
     * @param mixed $advanced_sources
     * 
     * @return array
     */
    public static function mergeSources($sources, $advanced_sources)
    {
        foreach ($advanced_sources as $advanced_source) {
            $key = array_search($advanced_source['sourceReference'], array_column(array_column($sources, 'source'), 'reference'));

            // If source exists in common sources array
            if ($key !== false) {
                // Only add installments
                $sources[$key]['installments']['list'] = array_merge($sources[$key]['installments']['list'], $advanced_source['installments']);
            } else {
                $sources[] = [
                    'source'       => $advanced_source['source'],
                    'installments' => [
                        'list' => $advanced_source['installments']
                    ]
                ];
            }
        }

        return $sources;
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
            CURLOPT_URL => "https://api.mobbex.com/p/operations/" . $id_transaction . "/refund",
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
            $tab .= '<tr><td><b>Número de Tarjeta: </b></td><td>' . $cardNumber . '</td></tr>
            <tr><td></td><td></td></tr>';
        }

        // Customer name
        if ($habienteName) {
            $tab .= '<tr><td><b>Nombre de Tarjeta-Habiente: </b></td><td>' . $habienteName . '</td></tr>
            <tr><td></td><td></td></tr>';
        }

        // Customer ID
        if (!empty($idHabiente)) {
            $tab .= '<tr><td><b>ID Tarjeta-habiente: </b></td><td>' . $idHabiente . '</td></tr>
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
                Shop::addSqlRestriction(),
            false
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
            var mbbx = {
                ...mbbx,
                ...<?= json_encode($vars) ?>
            }
        </script>
<?php
    }

    /**
     * Create an order from Cart.
     * 
     * @param string|int $cartId
     * @param array $transData
     * @param PaymentModule $module
     */
    public static function createOrder($cartId, $transData, $module)
    {
        try {
            $context = self::restoreContext($cartId);

            $module->validateOrder(
                $cartId,
                $transData['orderStatus'],
                (float) $context->cart->getOrderTotal(true, Cart::BOTH),
                $transData['name'],
                $transData['message'],
                [
                    '{transaction_id}' => $transData['transaction_id'],
                    '{message}' => $transData['message'],
                ],
                (int) $context->currency->id,
                false,
                $context->customer->secure_key
            );
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Error in Mobbex order creation: ' . $e->getMessage(), 3, null, 'Mobbex', $cartId, true, null);
        }
    }

    /**
     * Restore the context to process the order validation properly.
     * 
     * @param int|string $cartId 
     * 
     * @return Context $context 
     */
    protected static function restoreContext($cartId)
    {
        $context           = Context::getContext();
        $context->cart     = new Cart((int) $cartId);
        $context->customer = new Customer((int) Tools::getValue('customer_id'));
        $context->currency = new Currency((int) $context->cart->id_currency);
        $context->language = new Language((int) $context->customer->id_lang);

        if (!Validate::isLoadedObject($context->cart))
            PrestaShopLogger::addLog('Error getting context on Webhook: ', 3, null, 'Mobbex', $cartId, true, null);

        return $context;
    }

    /**
     * Add an script depending of context and prestashop version.
     * 
     * @param string $uri
     * @param mixed $type
     * @param Controller $controller
     */
    public static function addScript($uri, $remote = false, $controller = null)
    {
        if (!$controller)
            $controller = Context::getContext()->controller;

        if (_PS_VERSION_ >= '1.7' && $controller instanceof FrontController) {
            $controller->registerJavascript(sha1($uri), $uri, ['server' => $remote ? 'remote' : 'local']);
        } else {
            $controller->addJS($uri);
        }
    }

    /**
     * Check if it is in payment step.
     * 
     * @return bool
     */
    public static function isPaymentStep()
    {
        $controller = Context::getContext()->controller;

        if (_PS_VERSION_ < '1.7') {
            return $controller->step == $controller::STEP_PAYMENT;
        } else {
            // Make checkout process as accessible for prestashop backward compatibility
            $reflection = new ReflectionProperty($controller, 'checkoutProcess');
            $reflection->setAccessible(true);
            $checkoutProcess = $reflection->getValue($controller);

            foreach ($checkoutProcess->getSteps() as $step) {
                if ($step instanceof CheckoutPaymentStep && $step->isCurrent())
                    return true;
            }
        }

        return false;
    }

    /**
     * Retrieve plans filter fields data for product/category settings.
     * 
     * @param int|string $id
     * @param string $catalogType
     * 
     * @return array
     */
    public static function getPlansFilterFields($id, $catalogType = 'product')
    {
        $commonFields = $advancedFields = $sourceNames = [];

        // Get current checked plans from db
        $checkedCommonPlans   = json_decode(MobbexCustomFields::getCustomField($id, $catalogType, 'common_plans')) ?: [];
        $checkedAdvancedPlans = json_decode(MobbexCustomFields::getCustomField($id, $catalogType, 'advanced_plans')) ?: [];

        foreach (MobbexHelper::getSources() as $source) {
            // Only if have installments
            if (empty($source['installments']['list']))
                continue;

            // Create field array data
            foreach ($source['installments']['list'] as $plan) {
                $commonFields[$plan['reference']] = [
                    'id'    => 'common_plan_' . $plan['reference'],
                    'value' => !in_array($plan['reference'], $checkedCommonPlans),
                    'label' => $plan['name'] ?: $plan['description'],
                ];
            }
        }

        foreach (MobbexHelper::getSourcesAdvanced() as $source) {
            // Only if have installments
            if (empty($source['installments']))
                continue;

            // Save source name
            $sourceNames[$source['source']['reference']] = $source['source']['name'];

            // Create field array data
            foreach ($source['installments'] as $plan) {
                $advancedFields[$source['source']['reference']][] = [
                    'id'      => 'advanced_plan_' . $plan['uid'],
                    'value'   => in_array($plan['uid'], $checkedAdvancedPlans),
                    'label'   => $plan['name'] ?: $plan['description'],
                ];
            }
        }

        return compact('commonFields', 'advancedFields', 'sourceNames');
    }
}