<?php

namespace Mobbex\PS\Checkout\Models;

class Helper
{
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
    const K_PLANS_STYLES = 'MOBBEX_PLANS_STYLES';
    const K_PLANS_TEXT = 'MOBBEX_PLANS_TEXT';
    const K_PLANS_IMAGE_URL = 'MOBBEX_PLANS_IMAGE_URL';
    const K_MULTICARD = 'MOBBEX_MULTICARD';
    const K_UNIFIED_METHOD = 'MOBBEX_UNIFIED_METHOD';
    const K_MULTIVENDOR = 'MOBBEX_MULTIVENDOR';
    const K_ORDER_FIRST = 'MOBBEX_ORDER_FIRST';
    const K_CART_RESTORE = 'MOBBEX_CART_RESTORE';
    const K_PENDING_ORDER_DISCOUNT = 'MOBBEX_PENDING_ORDER_DISCOUNT';

    //Order statuses
    const K_OS_APPROVED = 'MOBBEX_OS_APPROVED';
    const K_OS_PENDING  = 'MOBBEX_OS_PENDING';
    const K_OS_WAITING  = 'MOBBEX_OS_WAITING';
    const K_OS_REJECTED = 'MOBBEX_OS_REJECTED';
    const K_ORDER_STATUS_APPROVED = 'MOBBEX_ORDER_STATUS_APPROVED';
    const K_ORDER_STATUS_FAILED   = 'MOBBEX_ORDER_STATUS_FAILED';
    const K_ORDER_STATUS_REJECTED = 'MOBBEX_ORDER_STATUS_REJECTED';
    const K_ORDER_STATUS_REFUNDED = 'MOBBEX_ORDER_STATUS_REFUNDED';

    const K_DEF_PLANS_TEXT = 'Planes Mobbex';
    const K_DEF_PLANS_STYLES =
'.mbbxWidgetOpenBtn {
    width: fit-content;
    min-height: 40px;
    border-radius: 6px;
    padding: 8px 18px; /* arriba/abajo, izquierda/derecha */
    font-size: 16px;
    color: #6f00ff;
    background-color: #ffffff;
    border: 2px solid #6f00ff; /* grosor, estilo de linea, color */
    cursor: pointer;
    /*box-shadow: 2px 2px 4px 0 rgba(0, 0, 0, .2);*/
}

.mbbxWidgetOpenBtn:hover {
    color: #ffffff;
    background-color: #6f00ff;
}';
    const K_DEF_PLANS_IMAGE_URL = 'https://res.mobbex.com/images/sources/mobbex.png';
    const K_DEF_MULTICARD = false;
    const K_DEF_MULTIVENDOR = false;

    const K_OWN_DNI = 'MOBBEX_OWN_DNI';
    const K_CUSTOM_DNI = 'MOBBEX_CUSTOM_DNI';

    public static function getUrl($path)
    {
        return \Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . $path;
    }

    public static function getModuleUrl($controller, $action = '', $path = '')
    {
        return \Mobbex\PS\Checkout\Models\Helper::getUrl("index.php?controller=$controller&module=mobbex&fc=module" . ($action  ? "&action=$action" : '') . $path);
    }

    public static function getPlatform()
    {
        return array(
            "name" => "prestashop",
            "version" => \Mobbex\PS\Checkout\Models\Config::MODULE_VERSION,
            "platform_version" => _PS_VERSION_,
        );
    }

    public static function getHeaders()
    {
        return array(
            'cache-control: no-cache',
            'content-type: application/json',
            'x-access-token: ' . \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_ACCESS_TOKEN),
            'x-api-key: ' . \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_API_KEY),
            'x-ecommerce-agent: PrestaShop/' . _PS_VERSION_ . ' Plugin/' . \Mobbex\PS\Checkout\Models\Config::MODULE_VERSION,
        );
    }

    public static function getOptions()
    {
        $custom_logo = \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_THEME_LOGO);

        // If store's logo option is disabled, use the one configured in mobbex
        $default_logo = null;
        if (!empty(\Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_THEME_SHOP_LOGO))) {
            $default_logo = \Tools::getShopDomainSsl(true, true) . _PS_IMG_ . \Configuration::get('PS_LOGO');
        }

        $theme_background = \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_THEME_BACKGROUND);
        $theme_primary = \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_THEME_PRIMARY);

        $theme = array(
            "type" => \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_THEME) ?: \Mobbex\PS\Checkout\Models\Helper::K_DEF_THEME,
            "header" => [
                "name" => \Configuration::get('PS_SHOP_NAME'),
                "logo" => !empty($custom_logo) ? $custom_logo : $default_logo,
            ],
            'background' => !empty($theme_background) ? $theme_background : null,
            'colors' => [
                'primary' => !empty($theme_primary) ? $theme_primary : null,
            ],
        );

        $options = array(
            'button' => (\Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_EMBED) == true),
            'domain' => \Context::getContext()->shop->domain,
            "theme" => $theme,
            // Will redirect automatically on Successful Payment Result
            "redirect" => [
                "success" => true,
                "failure" => false,
            ],
            "platform" => \Mobbex\PS\Checkout\Models\Helper::getPlatform(),
        );

        return $options;
    }

    public static function getReference($cart)
    {
        return 'ps_order_cart_' . $cart->id;
    }

    public static function createCheckout($module, $cart, $customer)
    {
        $curl = curl_init();
        $logger = new \Mobbex\PS\Checkout\Models\Logger();

        // Get items
        $items = array();
        $products = $cart->getProducts(true);

        foreach ($products as $product) {

            $image = \Image::getCover($product['id_product']);

            $prd = new \Product($product['id_product']);
            if ($prd->hasAttributes() && !empty($product['id_product_attribute'])) {
                $images = $prd->getCombinationImages(\Context::getContext()->language->id);
                $image = !empty($images[$product['id_product_attribute']][0]) ? $images[$product['id_product_attribute']][0] : $image;
            }

            $link = new \Link;
            $imagePath = $link->getImageLink($product['link_rewrite'], $image['id_image'], 'home_default');

            if(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($product['id_product'], 'product', 'subscription_enable') === 'yes') {
                $items[] = [
                    'type'      => 'subscription',
                    'reference' => \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($product['id_product'], 'product', 'subscription_uid')
                ];
            } else {
                $items[] = [
                    "image"       => 'https://' . $imagePath,
                    "description" => $product['name'],
                    "quantity"    => $product['cart_quantity'],
                    "total"       => round($product['price_wt'], 2),
                    "entity"      => \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_MULTIVENDOR) ? self::getEntityFromProduct($prd) : '',
                ];
            }

        }

        $shippingTotal = self::getShippingCost($cart);

        if ($shippingTotal) {
            $carrier = new \Carrier($cart->id_carrier);

            $items[] = [
                'total'       => $shippingTotal,
                'description' => $carrier->name . ' (envío)',
                'image'       => file_exists(_PS_SHIP_IMG_DIR_ . $cart->id_carrier . '.jpg') ? \Tools::getShopDomainSsl(true, true) . _THEME_SHIP_DIR_ . $cart->id_carrier . '.jpg' : null,
                'quantity'    => 1,
            ];
        }

        // Create data
        $data = array(
            'reference'    => \Mobbex\PS\Checkout\Models\Helper::getReference($cart),
            'currency'     => 'ARS',
            'description'  => 'Carrito #' . $cart->id,
            'test'         => (\Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_TEST_MODE) == true),
            'return_url'   => \Mobbex\PS\Checkout\Models\Helper::getModuleUrl('notification', 'return', '&id_cart=' . $cart->id . '&customer_id=' . $customer->id),
            'webhook'      => \Mobbex\PS\Checkout\Models\Helper::getModuleUrl('notification', 'webhook', '&id_cart=' . $cart->id . '&customer_id=' . $customer->id),
            'items'        => $items,
            'installments' => \Mobbex\PS\Checkout\Models\Helper::getInstallments(array_column($products, 'id_product')),
            'addresses'    => self::getAddresses($cart),
            'options'      => \Mobbex\PS\Checkout\Models\Helper::getOptions(),
            'total'        => (float) $cart->getOrderTotal(true, \Cart::BOTH),
            'customer'     => self::getCustomer($cart),
            'timeout'      => 5,
            'intent'       => defined('MOBBEX_CHECKOUT_INTENT') ? MOBBEX_CHECKOUT_INTENT : null,
            'wallet'       => (\Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_WALLET) && \Context::getContext()->customer->isLogged()),
            'multicard'    => (\Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_MULTICARD) == true),
            'multivendor'  => \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_MULTIVENDOR),
            'merchants'    => \Mobbex\PS\Checkout\Models\Helper::getMerchants($items),
        );

        $data = \Mobbex\PS\Checkout\Models\Registrar::executeHook('actionMobbexCheckoutRequest', true, $data, $products);

        if (!$data)
            return;

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

        $logger->log('debug', '\Mobbex\PS\Checkout\Models\Helper > createCheckout | Creating Checkout', $data);

        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {
            $logger->log('error', '\Mobbex\PS\Checkout\Models\Helper > createCheckout | Checkout Creation Error', $err);
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
     * @return array|null
     */
    public static function getPaymentData()
    {
        $cart = \Context::getContext()->cart;
        $customer = \Context::getContext()->customer;

        if (!$cart->id)
            return;

        return \Mobbex\PS\Checkout\Models\Registrar::executeHook('actionMobbexProcessPayment', false, $cart, $customer) ?: \Mobbex\PS\Checkout\Models\Helper::createCheckout(null, $cart, $customer);
    }

    /**
     * Get customer data formatted for checkout.
     * 
     * @param \Cart $cart
     *
     * @return array
     */
    public static function getCustomer($cart)
    {
        // Get address and customer data from context
        $address  = new \Address($cart->id_address_delivery);
        $customer = \Context::getContext()->customer;

        $firstName = empty($address->firstname) || $address->firstname == '.' ? $customer->firstname : $address->firstname;
        $lastName  = empty($address->lastname) || $address->lastname == '.' ? $customer->lastname : $address->lastname;

        return [
            'name'           => "$firstName $lastName",
            'email'          => $customer->email,
            'phone'          => $address->phone_mobile ?: $address->phone,
            'identification' => $customer->id ? \Mobbex\PS\Checkout\Models\Helper::getDni($customer->id) : null,
            'uid'            => $customer->id,
        ];
    }

    public static function getAddresses($cart)
    {
        $address = [];
        foreach (['shipping' => 'id_address_delivery', 'billing' => 'id_address_invoice'] as $type => $value) {

            $address = new \Address($cart->$value);
            $country = new \Country($address->id_country);
            $state   = new \State($address->id_state);

            $addresses[] = [
                'type'         => $type,
                'country'      => self::convertCountryCode($country->iso_code),
                'street'       => trim(preg_replace('/(\D{0})+(\d*)+$/', '', trim($address->address1))),
                'streetNumber' => str_replace(preg_replace('/(\D{0})+(\d*)+$/', '', trim($address->address1)), '', trim($address->address1)),
                'streetNotes'  => !empty($address->address2) ? $address->address2 : '',
                'zipCode'      => !empty($address->postcode) ? $address->postcode : '',
                'city'         => !empty($address->city) ? $address->city : '',
                'state'        => !empty($state->iso_code) ? $state->iso_code : ''
            ];
        }

        return $addresses;
    }

    public static function convertCountryCode($code)
    {
        $countries = include __DIR__ . '/../utils/country-codes.php' ?: [];

        return isset($countries[$code]) ? $countries[$code] : null;
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
            if (!empty($item['entity']))
                $merchants[] = ['uid' => $item['entity']];
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
            'parent'             => \Mobbex\PS\Checkout\Models\Helper::isParentWebhook($res['payment']['operation']['type']),
            'payment_id'         => isset($res['payment']['id']) ? $res['payment']['id'] : '',
            'description'        => isset($res['payment']['description']) ? $res['payment']['description'] : '',
            'status'             => (int) $res['payment']['status']['code'],
            'order_status'       => (int) \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_OS_PENDING),
            'status_message'     => isset($res['payment']['status']['message']) ? $res['payment']['status']['message'] : '',
            'source_name'        => !empty($res['payment']['source']['name']) ? $res['payment']['source']['name'] : 'Mobbex',
            'source_type'        => !empty($res['payment']['source']['type']) ? $res['payment']['source']['type'] : 'Mobbex',
            'source_reference'   => isset($res['payment']['source']['reference']) ? $res['payment']['source']['reference'] : '',
            'source_number'      => isset($res['payment']['source']['number']) ? $res['payment']['source']['number'] : '',
            'source_expiration'  => isset($res['payment']['source']['expiration']) ? json_encode($res['payment']['source']['expiration']) : '',
            'source_installment' => isset($res['payment']['source']['installment']) ? json_encode($res['payment']['source']['installment']) : '',
            'installment_name'   => isset($res['payment']['source']['installment']['description']) ? $res['payment']['source']['installment']['description'] : '',
            'source_url'         => isset($res['payment']['source']['url']) ? json_encode($res['payment']['source']['url']) : '',
            'cardholder'         => isset($res['payment']['source']['cardholder']) ? json_encode(($res['payment']['source']['cardholder'])) : '',
            'entity_name'        => isset($res['entity']['name']) ? $res['entity']['name'] : '',
            'entity_uid'         => isset($res['entity']['uid']) ? $res['entity']['uid'] : '',
            'customer'           => isset($res['customer']) ? json_encode($res['customer']) : '',
            'checkout_uid'       => isset($res['checkout']['uid']) ? $res['checkout']['uid'] : '',
            'total'              => isset($res['payment']['total']) ? $res['payment']['total'] : '',
            'currency'           => isset($res['checkout']['currency']) ? $res['checkout']['currency'] : '',
            'risk_analysis'      => isset($res['payment']['riskAnalysis']['level']) ? $res['payment']['riskAnalysis']['level'] : '',
            'data'               => json_encode($res),
            'created'            => isset($res['payment']['created']) ? $res['payment']['created'] : '',
            'updated'            => isset($res['payment']['updated']) ? $res['payment']['created'] : '',
        ];

        // Validate mobbex status and create order status
        $state = self::getState($data['status']);

        if ($state == 'onhold') {
            $data['order_status'] = (int) \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_OS_WAITING);
        } else if ($state == 'approved') {
            $data['order_status'] =  (int) (\Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_ORDER_STATUS_APPROVED) ?: \Configuration::get('PS_OS_' . 'PAYMENT'));
        } else if ($state == 'failed') {
            $data['order_status'] = (int) (\Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_ORDER_STATUS_FAILED) ?: \Configuration::get('PS_OS_' . 'ERROR'));
        } else if ($state == 'refunded') {
            $data['order_status'] = (int) \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_ORDER_STATUS_REFUNDED ?: \Configuration::get('PS_OS_' . 'REFUND'));
        } else if ($state == 'rejected') {
            $data['order_status'] = (int) (\Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_ORDER_STATUS_REJECTED) ?: \Configuration::get('PS_OS_' . 'ERROR'));
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
            if (\Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_MULTICARD) || \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_MULTIVENDOR))
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
                        'total'            => $transaction->total,
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
                    'coupon'      => \Mobbex\PS\Checkout\Models\Helper::generateCoupon($transaction)
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
        extract(\Mobbex\PS\Checkout\Models\Helper::getCustomDniColumn());
        // Check if dni column exists
        $custom_dni = \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($customer_id, 'customer', 'dni');
        if($custom_dni){
            return $custom_dni;
        }else if(!empty($dniColumn)){
            if(!empty(\DB::getInstance()->executeS("SHOW COLUMNS FROM $table LIKE '$dniColumn'")) || !empty(\DB::getInstance()->executeS("SHOW COLUMNS FROM $table LIKE '$identifier'"))){
            return \DB::getInstance()->getValue("SELECT $dniColumn FROM $table WHERE $identifier='$customer_id'");
            }
        }else{
            return;
        }
    }

    /**
     * Get the table & column name where dni field is stored from configuration.
     * @return array
     */
    public static function getCustomDniColumn()
    {
        //Default values
        $data = [
            'table'      => _DB_PREFIX_.'customer',
            'identifier' => 'customer_id',
        ];

        if (\Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_CUSTOM_DNI) != '') {
            foreach (explode(':', \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_CUSTOM_DNI)) as $key => $value) {
                if($key === 0 && count(explode(':', \Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_CUSTOM_DNI))) > 1){
                    $data['table'] = trim($value);
                } else if($key === 1){
                    $data['identifier'] = trim($value);
                } else {
                    $data['dniColumn'] = trim($value);
                }
            }
        }

        return $data;
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
     * Save sources in config data
     * 
     */
    public static function updateMobbexSources()
    {
        $names = $common = $advanced = [];

        foreach (self::getSources() as $source) {
            if (empty($source['installments']['list']))
                continue;

            // Format field data
            foreach ($source['installments']['list'] as $plan) {
                $common[$plan['reference']] = [
                    'id'    => "common_plan_$plan[reference]",
                    'key'   => $plan['reference'],
                    'label' => $plan['name'] ?: $plan['description'],
                ];
            }
        }

        foreach (self::getSourcesAdvanced() as $source) {
            if (empty($source['installments']))
                continue;

            // Save source name
            $names[$source['source']['reference']] = $source['source']['name'];

            // Format field data
            foreach ($source['installments'] as $plan) {
                $advanced[$source['source']['reference']][] = [
                    'id'    => "advanced_plan_$plan[uid]",
                    'key'   => $plan['uid'],
                    'label' => $plan['name'] ?: $plan['description'],
                ];
            }
        }

        // Save to db
        $shopId = \Context::getContext()->shop->id ?: null;
        \Mobbex\PS\Checkout\Models\CustomFields::saveCustomField($shopId, 'shop', 'source_names', json_encode($names));
        \Mobbex\PS\Checkout\Models\CustomFields::saveCustomField($shopId, 'shop', 'common_sources', json_encode($common));
        \Mobbex\PS\Checkout\Models\CustomFields::saveCustomField($shopId, 'shop', 'advanced_sources', json_encode($advanced));

        return compact('names', 'common', 'advanced');
    }

    /**
     * Retrieve installments checked on plans filter of each product.
     * 
     * @param array $products Array of products or their ids.
     * 
     * @return array
     */
    public static function getInstallments($products)
    {
        $installments = $inactivePlans = $activePlans = [];

        // Get plans from order products
        foreach ($products as $product) {
            $id = $product instanceOf \Product ? $product->id : $product;

            $inactivePlans = array_merge($inactivePlans, \Mobbex\PS\Checkout\Models\Helper::getInactivePlans($id));
            $activePlans   = array_merge($activePlans, \Mobbex\PS\Checkout\Models\Helper::getActivePlans($id));
        }

        // Add inactive (common) plans to installments
        foreach ($inactivePlans as $plan)
            $installments[] = '-' . $plan;

        // Add active (advanced) plans to installments (only if the plan is active on all products)
        foreach (array_count_values($activePlans) as $plan => $reps) {
            if ($reps == count($products))
                $installments[] = '+uid:' . $plan;
        }

        // Remove duplicated plans and return
        return array_values(array_unique($installments));
    }

    /**
     * Returns a query param with the installments of the product.
     * @param int $total
     * @param array $installments
     * @return string $query
     */
    public static function getInstallmentsQuery($total, $installments = [])
    {
        // Build query params and replace special chars
        return preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', http_build_query(compact('total','installments')));
    }

    /**
     * Get sources with common and advanced plans from mobbex.
     * 
     * @param int|float|null $total
     * @param array $installments
     * 
     * @return array
     */
    public static function getSources($total = null, $installments = [])
    {
        $curl   = curl_init();
        $query  = self::getInstallmentsQuery($total, $installments);
        $logger = new \Mobbex\PS\Checkout\Models\Logger();

        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.mobbex.com/p/sources" . ($query ? "?$query" : ''),
            CURLOPT_HTTPHEADER     => self::getHeaders(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);

        curl_close($curl);

        if ($error)
            $logger->log('error', "\Mobbex\PS\Checkout\Models\Helper > getSources | Sources Obtaining cURL Error $error", compact('total', 'installments'));

        $result = json_decode($response, true);

        if (empty($result['result']))
            $logger->log('error', '\Mobbex\PS\Checkout\Models\Helper > getSources | Sources Obtaining Error', compact('total', 'installments', 'response'));

        return isset($result['data']) ? $result['data'] : [];
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
        $curl   = curl_init();
        $logger = new \Mobbex\PS\Checkout\Models\Logger();

        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.mobbex.com/p/sources/rules/$rule/installments",
            CURLOPT_HTTPHEADER     => self::getHeaders(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);

        curl_close($curl);

        if ($error)
            $logger->log('error', "\Mobbex\PS\Checkout\Models\Helper > getSourcesAdvanced | Advanced Sources Obtaining cURL Error $error", ['rule' => $rule]);

        $result = json_decode($response, true);

        if (empty($result['result']))
            $logger->log('error', '\Mobbex\PS\Checkout\Models\Helper > getSourcesAdvanced | Advanced Sources Obtaining Error', ['rule' => $rule]);

        return isset($result['data']) ? $result['data'] : [];
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
            $productId = \Tools::getValue('id_product');

        $product = new \Product($productId);

        $inactivePlans = json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($productId, 'product', 'common_plans')) ?: [];

        foreach ($product->getCategories() as $categoryId)
            $inactivePlans = array_merge($inactivePlans, json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($categoryId, 'category', 'common_plans')) ?: []);

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
        return \Mobbex\PS\Checkout\Models\Config::MODULE_VERSION > \Db::getInstance()->getValue("SELECT version FROM " . _DB_PREFIX_ . "module WHERE name = 'mobbex'");
    }

    /**
     * Get database module upgrade URL.
     * 
     * @return string
     */
    public static function getUpgradeURL()
    {
        if (_PS_VERSION_ > '1.7') {
            return \Link::getUrlSmarty([
                'entity' => 'sf',
                'route'  => 'admin_module_updates',
            ]);
        } else {
            return \Context::getContext()->link->getAdminLink('AdminModules') . '&' . http_build_query([
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
            $productId = \Tools::getValue('id_product');

        $product = new \Product($productId);

        // Get plans from product and product categories
        $activePlans = json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($productId, 'product', 'advanced_plans')) ?: [];

        foreach ($product->getCategories() as $categoryId)
            $activePlans = array_merge($activePlans, json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($categoryId, 'category', 'advanced_plans')) ?: []);

        // Remove duplicated and return
        return array_unique($activePlans);
    }

    /**
     * Refund a Mobbex transaction.
     * 
     * @param int|string $total
     * @param string $paymentId
     *
     * @return bool
     * 
     * @throws \PrestaShopException
     */
    public static function processRefund($total, $paymentId)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://api.mobbex.com/p/operations/' . $paymentId . '/refund',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode(compact('total')),
            CURLOPT_HTTPHEADER     => self::getHeaders(),
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);

        curl_close($curl);

        if ($error)
            throw new \PrestaShopException("Mobbex Transaction Refund Curl Error: $error. Transaction #$paymentId");

        $res = json_decode($response, true);
        return !empty($res['result']) ? $res['result'] : false;
    }

    /**
     * Return payment data from a cart, this additional information is for the invoice pdf
     * 
     * @param int $id_cart
     * @return string
     */
    public static function getInvoiceData($id_cart)
    {

        $tab = '<table style="border: solid 1pt black; padding:0 10pt">';

        // Get Transaction Data
        $transactions = \Mobbex\PS\Checkout\Models\Transaction::getTransactions($id_cart);

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
        $order_id = (int) \Db::getInstance()->getValue(
            'SELECT `id_order`
            FROM `' . _DB_PREFIX_ . 'orders`
            WHERE `id_cart` = ' . (int) $cart_id .
                \Shop::addSqlRestriction(),
            false
        );

        // Exit if it does not exist in the database
        if (empty($order_id))
            return false;

        return $instance ? new \Order($order_id) : $order_id;
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
     * @param int|string $cartId
     * @param int|string $orderStatus
     * @param string $methodName
     * @param \PaymentModuleCore $module
     * @param bool $die
     * 
     * @return \Order|null
     */
    public static function createOrder($cartId, $orderStatus, $methodName, $module, $die = true)
    {
        try {
            $cart   = new \Cart($cartId);
            $logger = new \Mobbex\PS\Checkout\Models\Logger();

            // Validate order, remember to send secure key to avoid warning logs
            $module->validateOrder(
                $cartId,
                $orderStatus,
                self::getRoundedTotal($cart),
                $methodName,
                null,
                [],
                null,
                false,
                $cart->secure_key
            );

            return self::getOrderByCartId($cartId, true);
        } catch (\Exception $e) {
            $logger->log($die ? 'fatal' : 'error' , '\Mobbex\PS\Checkout\Models\Helper > createOrder | Order Creation Error ' . $e->getMessage(), compact('cartId', 'orderStatus', 'methodName'));
        }
    }

    /**
     * Add an asset file depending of context and prestashop version.
     * 
     * @param string $uri
     * @param string $type
     * @param bool $remote
     * @param bool $addVersion
     * @param null|\Controller $controller
     */
    public static function addAsset($uri, $type = 'js', $remote = true, $addVersion = true, $controller = null)
    {
        if (!$controller)
            $controller = \Context::getContext()->controller;

        if ($addVersion)
            $uri .= '?ver=' . \Mobbex\PS\Checkout\Models\Config::MODULE_VERSION;

        if (\Configuration::get('MOBBEX_FORCE_ASSETS')) {
            echo $type == 'js' ? "<script type='text/javascript' src='$uri'></script>" : "<link rel='stylesheet' href='$uri'>";
        } else if (_PS_VERSION_ >= '1.7' && $controller instanceof \FrontController) {
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
        $controller = \Context::getContext()->controller;

        if (_PS_VERSION_ < '1.7') {
            return $controller->step == $controller::STEP_PAYMENT;
        } else {
            // Make checkout process as accessible for prestashop backward compatibility
            $reflection = new \ReflectionProperty($controller, 'checkoutProcess');
            $reflection->setAccessible(true);
            $checkoutProcess = $reflection->getValue($controller);

            foreach ($checkoutProcess->getSteps() as $step) {
                if ($step instanceof \CheckoutPaymentStep && $step->isCurrent())
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
        $shopId = \Context::getContext()->shop->id ?: null;

        // Try to get sources from db
        $sources = [
            'names'    => json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($shopId, 'shop', 'source_names'), true)     ?: [],
            'common'   => json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($shopId, 'shop', 'common_sources'), true)   ?: [],
            'advanced' => json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($shopId, 'shop', 'advanced_sources'), true) ?: [],
        ];

        // Get current checked plans
        $values = [
            'common'   => json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($id, $catalogType, 'common_plans'), true)   ?: [],
            'advanced' => json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($id, $catalogType, 'advanced_plans'), true) ?: [],
        ];

        if (!$sources['common'] || !$sources['advanced'])
            $sources = self::updateMobbexSources();

        return compact('sources', 'values');
    }

    /**
     * Retrieve entity configured by product or parent categories.
     * 
     * @param \Product $product
     * 
     * @return array
     */
    public static function getEntityFromProduct($product)
    {
        $productEntity = \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($product->id, 'product', 'entity');

        if ($productEntity)
            return $productEntity;

        // Try to get from their categories
        foreach ($product->getCategories() as $categoryId) {
            $entity = \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($categoryId, 'category', 'entity');

            if ($entity)
                return $entity;
        }
    }

    /**
     * Create an order from current Cart and recreate cart if it fail.
     * 
     * @param \Module $module
     * 
     * @return bool Order creation result.
     */
    public static function processOrder($module)
    {
        $cart   = \Context::getContext()->cart;
        $logger = new \Mobbex\PS\Checkout\Models\Logger();

        if (!\Validate::isLoadedObject($cart)) {
            $logger->log('error', '\Mobbex\PS\Checkout\Models\Helper > processOrder | Error Loading Cart On Order Process', $_REQUEST);

            return false;
        }

        // First, try to get from db
        $order = self::getOrderByCartId($cart->id, true);

        // Create order if not exists
        if (!$order) {
            $order = self::createOrder(
                $cart->id,
                \Configuration::get(self::K_OS_PENDING),
                'Mobbex',
                $module,
                false
            );

            // Add order expiration task
            if (!self::needUpgrade()) {
                $task = new \Mobbex\PS\Checkout\Models\Task(
                    null,
                    'actionMobbexExpireOrder',
                    \Configuration::get('MOBBEX_EXPIRATION_INTERVAL') ?: 3,
                    \Configuration::get('MOBBEX_EXPIRATION_PERIOD') ?: 'day',
                    1,
                    $order->id
                );
                $task->add();
            }
        }
        
        // Validate that order looks good
        if (!$order || !\Validate::isLoadedObject($order) || !$order->total_paid) {
            $logger->log('error', '\Mobbex\PS\Checkout\Models\Helper > processOrder | Error Creating/Loading Order On Order Process', ['cart_id' => $cart->id]);
            self::restoreCart($cart); 

            return false;
        }

        //refund stock
        if(!\Configuration::get(\Mobbex\PS\Checkout\Models\Helper::K_PENDING_ORDER_DISCOUNT)){
            foreach ($order->getProductsDetail() as $product) {
                if (!\StockAvailable::dependsOnStock($product['product_id']))
                    \StockAvailable::updateQuantity($product['product_id'], $product['product_attribute_id'], (int) $product['product_quantity'], $order->id_shop);
            }
        }

        return true;
    }

    /**
     * Duplicate a Cart instance and save it to context.
     * 
     * @param \Cart $cart
     * 
     * @return \Cart|null New Cart.
     */
    public static function restoreCart($cart)
    {
        $result = $cart->duplicate();
        $logger = new \Mobbex\PS\Checkout\Models\Logger();

        if (!$result || !\Validate::isLoadedObject($result['cart']) || !$result['success'])
            return $logger->log('error', '\Mobbex\PS\Checkout\Models\Helper > createCheckout | Error Creating/Loading Order On Order Process', ['cart id' => isset($cart->id) ? $cart->id : 0]);

        \Context::getContext()->cookie->id_cart = $result['cart']->id;
        $context = \Context::getContext();
        $context->cart = $result['cart'];
        \CartRule::autoAddToCart($context);
        \Context::getContext()->cookie->write();

        return $result['cart'];
    }

    /**
     * Retrieve final shipping cost for the given cart.
     * 
     * @param \Cart $cart
     * 
     * @return float|int 
     */
    public static function getShippingCost($cart)
    {
        // Check if cart has a free shipping voucher
        foreach ($cart->getCartRules() as $rule)
            if ($rule['free_shipping'] && !$rule['carrier_restriction'])
                return 0;

        return (float) $cart->getTotalShippingCost();
    }

    /**
     * Get rounded total from a Cart.
     * 
     * @param int|\Cart $cart
     * 
     * @return float 
     */
    public static function getRoundedTotal($cart)
    {
        // Instance cart if needed
        if (!is_object($cart))
            $cart = new \Cart($cart);

        // Instance context to get computing precision
        $context = \Context::getContext();

        return (float) \Tools::ps_round(
            (float) $cart->getOrderTotal(),
            method_exists($context, 'getComputingPrecision') ? $context->getComputingPrecision() : 2
        );
    }
}
