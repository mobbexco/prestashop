<?php

/**
 * Mobbex.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 2.5.0
 * @see     PaymentModuleCore
 */

/**
 * Payment Provider Class
 */
class MobbexHelper
{
    const MOBBEX_VERSION = '2.5.0';

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
    const K_MULTIVENDOR = 'MOBBEX_MULTIVENDOR';

    const K_DEF_PLANS_TEXT = 'Planes Mobbex';
    const K_DEF_PLANS_TEXT_COLOR = '#ffffff';
    const K_DEF_PLANS_BACKGROUND = '#8900ff';
    const K_DEF_PLANS_IMAGE_URL = 'https://res.mobbex.com/images/sources/mobbex.png';
    const K_DEF_PLANS_PADDING = '4px 18px';
    const K_DEF_PLANS_FONT_SIZE = '16px';
    const K_DEF_PLANS_THEME = MobbexHelper::K_THEME_LIGHT;
    const K_DEF_MULTICARD = false;
    const K_DEF_MULTIVENDOR = false;

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
                "image"       => 'https://' . $imagePath,
                "description" => $product['name'],
                "quantity"    => $product['cart_quantity'],
                "total"       => round($product['price_wt'], 2),
                "entity"      => Configuration::get(MobbexHelper::K_MULTIVENDOR) ? self::getEntityFromProduct($prd) : '',
            ];
        }

        // Create data
        $data = array(
            'reference'    => MobbexHelper::getReference($cart),
            'currency'     => 'ARS',
            'description'  => 'Carrito #' . $cart->id,
            'test'         => (Configuration::get(MobbexHelper::K_TEST_MODE) == true),
            'return_url'   => MobbexHelper::getModuleUrl('notification', 'return', '&id_cart=' . $cart->id . '&customer_id=' . $customer->id),
            'webhook'      => MobbexHelper::getModuleUrl('notification', 'webhook', '&id_cart=' . $cart->id . '&customer_id=' . $customer->id),
            'items'        => $items,
            'installments' => MobbexHelper::getInstallments($products),
            'options'      => MobbexHelper::getOptions(),
            'total'        => (float) $cart->getOrderTotal(true, Cart::BOTH),
            'customer'     => self::getCustomer($cart),
            'timeout'      => 5,
            'intent'       => defined('MOBBEX_CHECKOUT_INTENT') ? MOBBEX_CHECKOUT_INTENT : null,
            'wallet'       => (Configuration::get(MobbexHelper::K_WALLET) && Context::getContext()->customer->isLogged()),
            'multicard'    => (Configuration::get(MobbexHelper::K_MULTICARD) == true),
            'multivendor'  => Configuration::get(MobbexHelper::K_MULTIVENDOR),
            'merchants'    => MobbexHelper::getMerchants($items),
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

    /**
     * Return a json decode array with al the merchants from the items list.
     * 
     * @param array $items
     * @return array
     * 
     */
    public static function getMerchants($items)
    {

        $merchants = [];

        //Get the merchants from items list
        foreach ($items as $item) {
            if ($item['entity']) {
                $merchants[] = ['uid' => $item['entity']];
            }
        }

        return $merchants;
    }

    /**
     * Receives an array with the weebhook generates the order status and returns an array with organized data
     * 
     * @param array $res
     * @return array $data
     * 
     */
    public static function getTransactionData($res)
    {
        $data = [
            'parent'             => MobbexHelper::isParentWebhook($res['payment']['operation']['type']),
            'payment_id'         => isset($res['payment']['id']) ? $res['payment']['id'] : '',
            'description'        => isset($res['payment']['description']) ? $res['payment']['description'] : '',
            'status'             => (int) $res['payment']['status']['code'],
            'order_status'       => (int) Configuration::get(MobbexHelper::K_OS_PENDING),
            'status_message'     => isset($res['payment']['status']['message']) ? $res['payment']['status']['message'] : '',
            'source_name'        => !empty($res['payment']['source']['name']) ? $res['payment']['source']['name'] : 'Mobbex',
            'source_type'        => !empty($res['payment']['source']['type']) ? $res['payment']['source']['type'] : 'Mobbex',
            'source_reference'   => isset($res['payment']['source']['reference']) ? $res['payment']['source']['reference'] : '',
            'source_number'      => isset($res['payment']['source']['number']) ? $res['payment']['source']['number'] : '',
            'source_expiration'  => isset($res['payment']['source']['expiration']) ? json_encode($res['payment']['source']['expiration']) : '',
            'source_installment' => isset($res['payment']['source']['installment']) ? json_encode($res['payment']['source']['installment']) : '',
            'installment_name'   => isset($res['payment']['source']['installment']['description']) ? json_encode($res['payment']['source']['installment']['description']) : '',
            'source_url'         => isset($res['payment']['source']['url']) ? json_encode($res['payment']['source']['url']) : '',
            'cardholder'         => isset($res['payment']['source']['cardholder']) ? json_encode(($res['payment']['source']['cardholder'])) : '',
            'entity_name'        => isset($res['entity']['name']) ? $res['entity']['name'] : '',
            'entity_uid'         => isset($res['entity']['uid']) ? $res['entity']['uid'] : '',
            'customer'           => isset($res['customer']) ? json_encode($res['customer']) : '',
            'checkout_uid'       => isset($res['checkout']['uid']) ? $res['checkout']['uid'] : '',
            'total'              => isset($res['checkout']['total']) ? $res['checkout']['total'] : '',
            'currency'           => isset($res['checkout']['currency']) ? $res['checkout']['currency'] : '',
            'risk_analysis'      => isset($res['payment']['riskAnalysis']['level']) ? $res['payment']['riskAnalysis']['level'] : '',
            'data'               => json_encode($res),
            'created'            => isset($res['payment']['created']) ? $res['payment']['created'] : '',
            'updated'            => isset($res['payment']['updated']) ? $res['payment']['created'] : '',
        ];


        // Validate mobbex status and create order status
        $state = self::getState($data['status']);

        if ($state == 'onhold') {
            $data['order_status'] = (int) Configuration::get(MobbexHelper::K_OS_WAITING);
        } else if ($state == 'approved') {
            $data['order_status'] = (int) Configuration::get('PS_OS_PAYMENT');
        } else if ($state == 'failed') {
            $data['order_status'] = (int) Configuration::get('PS_OS_ERROR');
        } else if ($state == 'refunded') {
            $data['order_status'] = (int) Configuration::get('PS_OS_REFUND');
        } else if ($state == 'rejected') {
            $data['order_status'] = (int) Configuration::get(MobbexHelper::K_OS_REJECTED) ?: Configuration::get('PS_OS_ERROR');
        }

        return $data;
    }

    /**
     * Receives the webhook "opartion type" and return true if the webhook is parent and false if not
     * 
     * @param string $operationType
     * @return bool true|false
     * 
     */
    public static function isParentWebhook($operationType)
    {
        if ($operationType === "payment.v2") {
            if (!empty(Configuration::get(MobbexHelper::K_MULTICARD)) || !empty(Configuration::get(MobbexHelper::K_MULTIVENDOR)))
                return false;
        }
        return true;
    }

    /**
     * Return the list of sources from the weebhook and filter them
     * 
     * @param array $transactions
     * @return array $sources
     * 
     */
    public static function getWebhookSources($transactions)
    {

        $sources = [];

        foreach ($transactions as $key => $transaction) {
            if($transaction->parent == "1" && count($transactions) > 1) {
                unset($transactions[$key]);
            } else {
                if ($transaction->source_name != 'mobbex') {

                    $sources[] = [
                        'source_type'      => $transaction->source_type,
                        'source_name'      => $transaction->source_name,
                        'source_number'    => $transaction->source_number,
                        'installment_name' => $transaction->installment_name,
                        'source_url'       => $transaction->source_url,
                    ];
                }
            }
        }

        foreach ($sources as $key => $value) {
            if ($key > 0 && $value['source_number'] == $sources[0]['source_number'])
                unset($sources[$key]);
        }
       
        return $sources;
    }

    /**
     * Return the list of entities from the weebhook and filter them.
     * 
     * @param array $transactions
     * @return array $entities
     * 
     */
    public static function getWebhookEntities($transactions)
    {
        $entities = [];

        foreach ($transactions as $key => $transaction) {
           
            if($transaction->parent == "1" && count($transactions) > 1) {
                unset($transactions[$key]);
            } else {
                $entities[] = [
                    'entity_uid'  => $transaction->entity_uid,
                    'entity_name' => $transaction->entity_name,
                    'total'       => $transaction->total,
                    'coupon'      => MobbexHelper::generateCoupon($transaction)
                ];
            }
            
        }

        foreach ($entities as $key => $value) {
            if ($key > 0 && $value['entity_uid'] == $entities[0]['entity_uid'])
                unset($entities[$key]);
        }

        return $entities;
    }

    /**
     * Return the coupon of the transaction.
     * 
     * @param array $transactions
     * @return string $coupon
     * 
     */
    public static function generateCoupon($transaction)
    {
        $coupon = "https://mobbex.com/console/" . $transaction->entity_uid . "/operations/?oid=" . $transaction->payment_id;
        return $coupon;
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
     * Get sources with common and advanced plans from mobbex.
     * 
     * @param integer|null $total
     * 
     * @return array
     */
    public static function getSources($total = null, $inactivePlans = null, $activePlans = null)
    {
        $curl = curl_init();

        $data = $total ? '?total=' . $total : null;

        $data .= self::getInstallmentsQuery($inactivePlans, $activePlans);

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
     * Returns a query param with the installments of the product.
     * @param array $inactivePlans
     * @param array $activePlans
     */
    public static function getInstallmentsQuery($inactivePlans = null, $activePlans = null)
    {

        $installments = [];

        //get plans
        if ($inactivePlans) {
            foreach ($inactivePlans as $plan) {
                $installments[] = "-$plan";
            }
        }

        if ($activePlans) {
            foreach ($activePlans as $plan) {
                $installments[] = "+uid:$plan";
            }
        }

        //Build query param
        $query = http_build_query(['installments' => $installments]);
        $query = preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', $query);

        return $query;
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
     * Return true if the module need upgrade the database.
     * 
     * @return bool
     */
    public static function needUpgrade()
    {
        return self::MOBBEX_VERSION > Db::getInstance()->getValue("SELECT version FROM " . _DB_PREFIX_ . "module WHERE name = 'mobbex'");
    }

    /**
     * Get database module upgrade URL.
     * 
     * @return string
     */
    public static function getUpgradeURL()
    {
        if (_PS_VERSION_ > '1.7') {
            return Link::getUrlSmarty([
                'entity' => 'sf',
                'route'  => 'admin_module_updates',
            ]);
        } else {
            return Context::getContext()->link->getAdminLink('AdminModules') . '&' . http_build_query([
                'checkAndUpdate' => true,
                'module_name'    => 'mobbex'
            ]);
        }
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

        $tab = '<table style="border: solid 1pt black; padding:0 10pt">';

        // Get Transaction Data
        $transactions = MobbexTransaction::getTransactions($id_cart);

        // Check if data exists
        if (empty($transactionData) || !is_array($transactionData)) {
            return false;
        }

        foreach ($transactions as $trx) {

            $trxData = json_decode($trx->data);

            $cardNumber   = !empty($trx->source_number) ? $trx->source_number : false;
            $habienteName = !empty($trx->entity_name) ? $trx->entity_name : false;
            $idHabiente   = !empty($trxData['customer']['identification']) ? $trxData['customer']['identification'] : false;

            // Card number
            if ($cardNumber) {
                $tab .= '<tr><td><b>Número de Tarjeta: </b></td><td>' . $cardNumber . '</td></tr><tr><td></td><td></td></tr>';
            }

            // Customer name
            if ($habienteName) {
                $tab .= '<tr><td><b>Nombre de Tarjeta-Habiente: </b></td><td>' . $habienteName . '</td></tr><tr><td></td><td></td></tr>';
            }

            // Customer ID
            if (!empty($idHabiente)) {
                $tab .= '<tr><td><b>ID Tarjeta-habiente: </b></td><td>' . $idHabiente . '</td></tr><tr><td></td><td></td></tr>';
            }
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
    public static function createOrder($cartId, $data, $module)
    {

        try {
            $context = self::restoreContext($cartId);

            $module->validateOrder(
                $cartId,
                $data['order_status'],
                (float) $context->cart->getOrderTotal(true, Cart::BOTH),
                $data['source_name'],
                $data['message'],
                [
                    '{transaction_id}' => $data['payment_id'],
                    '{message}' => $data['message'],
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
     * Add an asset file depending of context and prestashop version.
     * 
     * @param string $uri
     * @param string $type
     * @param bool $remote
     * @param null|Controller $controller
     */
    public static function addAsset($uri, $type = 'js', $remote = true, $controller = null)
    {
        if (!$controller)
            $controller = Context::getContext()->controller;

        if (_PS_VERSION_ >= '1.7' && $controller instanceof FrontController) {
            $params = ['server' => $remote ? 'remote' : 'local'];
            $type == 'js' ? $controller->registerJavascript(sha1($uri), $uri, $params) : $controller->registerStylesheet(sha1($uri), $uri, $params);
        } else {
            $type == 'js' ? $controller->addJS($uri) : $controller->addCSS($uri, 'all', null, false);
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

    /**
     * Retrieve entity configured by product or parent categories.
     * 
     * @param Product $product
     * 
     * @return array
     */
    public static function getEntityFromProduct($product)
    {
        $productEntity = MobbexCustomFields::getCustomField($product->id, 'product', 'entity');

        if ($productEntity)
            return $productEntity;

        // Try to get from their categories
        foreach ($product->getCategories() as $categoryId) {
            $entity = MobbexCustomFields::getCustomField($categoryId, 'category', 'entity');

            if ($entity)
                return $entity;
        }
    }
}
