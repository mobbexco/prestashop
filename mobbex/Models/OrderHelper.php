<?php

namespace Mobbex\PS\Checkout\Models;

class OrderHelper
{
    /**
     * Add a path to the store domain by passing a url 
     * 
     * @param string $url
     * 
     * @return string domain
     */
    public static function getUrl($url)
    {
        return \Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . $url;
    }

    /**
     * Create a url passing the controller, action and path
     * 
     * @param string $controller
     * @param string $action
     * @param string $path
     * 
     * @return string $url
     * 
     */ 
    public static function getModuleUrl($controller, $action = '', $path = '')
    {
        $url = ("index.php?controller=$controller&module=mobbex&fc=module" . ($action  ? "&action=$action" : '') . $path);
        //Add xdebug param to webhook
        if ($action == 'webhook' && \Configuration::get('MOBBEX_DEBUG'))
            $url .= '&XDEBUG_SESSION_START=PHPSTORM';
            
        return self::getUrl($url);
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
    public function createOrder($cartId, $orderStatus, $methodName, $module, $die = true)
    {
        try {
            $db   = \Db::getInstance();
            $cart = new \Cart($cartId);

            // Validate order, remember to send secure key to avoid warning logs
            $module->validateOrder(
                $cartId,
                $orderStatus,
                $this->getRoundedTotal($cart),
                $methodName,
                null,
                [],
                null,
                false,
                $cart->secure_key
            );
        } catch (\Exception $e) {
            Logger::log(
                $die ? 'fatal' : 'error', 'Helper > createOrder | Order Creation Error ' . $e->getMessage(), [
                    'cart_id'             => $cartId,
                    'order_status'        => $orderStatus,
                    'method_name'         => $methodName,
                    'query_error_message' => $db->getMsgError(),
                    'query_error_number'  => $db->getNumberError(),
                ]
            );
        }

        return $this->getOrderByCartId($cartId, true);
    }

    /**
     * Create an order from current Cart and recreate cart if it fail.
     * 
     * @param \Module $module
     * 
     * @return bool Order creation result.
     */
    public function processOrder($module)
    {
        $cart   = \Context::getContext()->cart;

        if (!\Validate::isLoadedObject($cart)) {
            Logger::log('error', 'Helper > processOrder | Error Loading Cart On Order Process', $_REQUEST);

            return false;
        }

        // First, try to get from db
        $order = $this->getOrderByCartId($cart->id, true);

        // Create order if not exists
        if (!$order) {
            $order = $this->createOrder(
                $cart->id,
                \Configuration::get('MOBBEX_OS_PENDING'),
                'Mobbex',
                $module,
                false
            );

            // Add order expiration task
            if (!\Mobbex\PS\Checkout\Models\Updater::needUpgrade()) {
                $task = new Task(
                    null,
                    'actionMobbexExpireOrder',
                    Config::$settings['expiration_interval'] ?: 3,
                    Config::$settings['expiration_period'] ?: 'day',
                    1,
                    $order->id
                );
                $task->add();
            }
        }

        // Validate that order looks good
        if (!$order || !\Validate::isLoadedObject($order) || !$order->total_paid) {
            Logger::log('error', 'Helper > processOrder | Error Creating/Loading Order On Order Process', ['cart_id' => $cart->id]);
            $this->restoreCart($cart);

            return false;
        }

        //refund stock
        if (Config::$settings['pending_discount'])
            return true;

        foreach ($order->getProductsDetail() as $product) {
            $shouldUpdate = true;

            // StockAvailable is deprecated beyond 1.7.8
            if (method_exists('StockAvailable', 'dependsOnStock'))
                $shouldUpdate = !\StockAvailable::dependsOnStock(
                    (int) $product['product_id']
                );

            if ($shouldUpdate)
                \StockAvailable::updateQuantity(
                    (int) $product['product_id'],
                    (int) $product['product_attribute_id'],
                    (int) $product['product_quantity'],
                    (int) $order->id_shop
                );
        }

        return true;
    }

    /**
     * Check if it is in payment step.
     * 
     * @return bool
     */
    public function isPaymentStep()
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
     * Get the payment data
     * 
     * @param bool $draft
     * 
     * @return array|null
     * 
     */
    public function getPaymentData($draft = false)
    {
        // Get cart and customer from context
        $config   = Config::$settings;
        $cart     = \Context::getContext()->cart;
        $customer = \Context::getContext()->customer;

        if (!$cart->id)
            return;

        // Replace checkout if is it needed
        $replaceCheckout = Registrar::executeHook('actionMobbexProcessPayment', false, $cart, $customer);

        if ($replaceCheckout)
            return $replaceCheckout;

        // Return if the draft is not needed
        if ($draft && !$config['payment_methods'] && !$config['wallet'] && !$config['transparent_enabled'])
            return;

        return $this->createCheckout($cart, $customer, $draft);
    }

    /**
     * Creates Mobbex Checkout
     * 
     * @param object $cart
     * @param object $customer
     * @param bool   $draft
     * 
     * @return array response
     * 
     */
    public function createCheckout($cart, $customer, $draft)
    {
        // Generate reference
        $reference = \Mobbex\Modules\Checkout::generateReference($cart->id) . ($draft ? '_DRAFT_CHECKOUT' : '');

        // Get items
        $items = array();
        // Checks if there´s any cart rule and returns an array of products with the discounts
        $products =(new \Mobbex\PS\Checkout\Models\PriceCalculator($cart))->getCartRules();
        
        //Get products active plans
        extract(\Mobbex\PS\Checkout\Models\Config::getProductsPlans($products));

        foreach ($products as $product) {

            $image = \Image::getCover($product['id_product']);
            $prd   = new \Product($product['id_product']);

            // Get product image
            if ($prd->hasAttributes() && !empty($product['id_product_attribute'])) {
                $images = $prd->getCombinationImages(\Context::getContext()->language->id);
                $image = !empty($images[$product['id_product_attribute']][0]) ? $images[$product['id_product_attribute']][0] : $image;
            }

            // Get link from product image
            $link      = new \Link;
            $imagePath = !empty($image) ? $link->getImageLink($product['link_rewrite'], $image['id_image'], 'home_default') : '';
            
            // Get items metadata
            if (CustomFields::getCustomField($product['id_product'], 'product', 'subscription_enable') === 'yes') {
                $items[] = [
                    'type'      => 'subscription',
                    'reference' => CustomFields::getCustomField($product['id_product'], 'product', 'subscription_uid')
                ];
            } else {
                $items[] = [
                    "image"       => 'https://' . $imagePath,
                    "description" => $product['name'],
                    "quantity"    => $product['cart_quantity'],
                    "total"       => $product['total_wt'],
                    "entity"      => $this->getProductEntity($prd),
                ];
            }
        }

        $shippingTotal = $this->getShippingCost($cart);

        // Get carrier instance data and add it to items array
        if ($shippingTotal) {
            $carrier = new \Carrier($cart->id_carrier);

            $items[] = [
                'total'       => $shippingTotal,
                'description' => $carrier->name . ' (envío)',
                'image'       => file_exists(_PS_SHIP_IMG_DIR_ . $cart->id_carrier . '.jpg') ? \Tools::getShopDomainSsl(true, true) . _THEME_SHIP_DIR_ . $cart->id_carrier . '.jpg' : null,
                'quantity'    => 1,
            ];
        }

        // Set return url
        $return_url   = self::getModuleUrl('notification', 'return', '&id_cart=' . $cart->id . '&customer_id=' . $customer->id);
        $customerData = $this->getCustomer($cart);
        
        if(empty($customerData['identification'])){
            Logger::log('error', 'OrderHelper > getDni | El cliente no tiene registrado un DNI', ['customer_id' => $customer ? $customer->id : '']);
            
            // If commerce use mobbex dni redirect to customer page.
            if(Config::$settings['mobbex_dni'] && _PS_VERSION_ >= Config::PS17)
                \Tools::redirect(\Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('notification', 'redirect', '&type=warning&url=identity&message=missing_dni'));
        }

        // Attempt to create a payment checkout
        try {
            $mobbexCheckout = new \Mobbex\Modules\Checkout(
                $cart->id,
                (float) $cart->getOrderTotal(true, \Cart::BOTH),
                $return_url,
                self::getModuleUrl('notification', 'webhook', '&id_cart=' . $cart->id . '&customer_id=' . $customer->id . "&mbbx_token=" . \Mobbex\Repository::generateToken()),
                \Currency::getCurrency($cart->id_currency)['iso_code'],
                $items,
                \Mobbex\Repository::getInstallments($products, [], $advanced_plans),
                $customerData,
                $this->getAddresses($cart),
                $draft ? 'none' : null,
                'actionMobbexCheckoutRequest',
                null,
                $reference
            );
        } catch (\Mobbex\Exception $e) {
            Logger::log('error', "Checkout > getCheckout | Fail getting checkout", $e->getMessage());
            return false;
        }

        Logger::log('debug', "Checkout Response: ", $mobbexCheckout->response);

        $mobbexCheckout->response['return_url'] = $return_url;
        
        return $mobbexCheckout->response;
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
    public function addAsset($uri, $type = 'js', $remote = true, $addVersion = true, $controller = null)
    {
        if (!$controller)
            $controller = \Context::getContext()->controller;

        if ($addVersion)
            $uri .= '?ver=' . Config::MODULE_VERSION;

        if (!empty(Config::$settings['force_assets']) && Config::$settings['force_assets'] == \Tools::getValue('controller')) {
            echo $type == 'js' ? "<script type='text/javascript' src='$uri'></script>" : "<link rel='stylesheet' href='$uri'>";
        } else if (_PS_VERSION_ >= '1.7' && $controller instanceof \FrontController) {
            $params = ['server' => $remote ? 'remote' : 'local'];
            $type == 'js' ? $controller->registerJavascript(sha1($uri), $uri, $params) : $controller->registerStylesheet(sha1($uri), $uri, $params);
        } else {
            $type == 'js' ? $controller->addJS($uri) : $controller->addCSS($uri, 'all', null, false);
        }
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
            window.mbbx = window.mbbx || {};
            window.mbbx = Object.assign(window.mbbx, <?= json_encode($vars) ?>);
            var mbbx = window.mbbx;
        </script>
<?php
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
    public function getOrderByCartId($cart_id, $instance = false)
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
     * Get customer data formatted for checkout.
     * 
     * @param \Cart $cart
     *
     * @return array
     */
    public function getCustomer($cart)
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
            'identification' => $customer->id ? $this->getDni($customer->id) : null,
            'uid'            => $customer->id,
            'createdAt'      => \Mobbex\dateToTime($customer->date_add),
        ];
    }

    public function getAddresses($cart)
    {
        $address = [];
        foreach (['shipping' => 'id_address_delivery', 'billing' => 'id_address_invoice'] as $type => $value) {

            $address = new \Address($cart->$value);
            $country = new \Country($address->id_country);
            $state   = new \State($address->id_state);

            $addresses[] = [
                'type'         => $type,
                'country'      => \Mobbex\Repository::convertCountryCode($country->iso_code),
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

    /**
     * Get customer dni from Mobbex or PS table
     * 
     * @param mixed $customer_id
     * 
     * @return mixed customer dni
     */
    public function getDni($customer_id)
    {
        //get custom dni column
        extract(Config::getCustomDniColumn());
        // Check if dni column exists
        $custom_dni = CustomFields::getCustomField($customer_id, 'customer', 'dni');
        
        // Try to get dni from the mobbex custom fields table
        if ($custom_dni) {
            return $custom_dni;
        // Try to get dni from the prestashop customer table
        } else if (!empty($dniColumn)) {
            if (!empty(\DB::getInstance()->executeS("SHOW COLUMNS FROM $table LIKE '$dniColumn'")) || !empty(\DB::getInstance()->executeS("SHOW COLUMNS FROM $table LIKE '$identifier'"))) {
                return \DB::getInstance()->getValue("SELECT $dniColumn FROM $table WHERE $identifier='$customer_id'");
            }
        } else {
            return '';
        }
    }

    /**
     * Return entity assigned to a given product.
     * 
     * @param \Product $product
     * 
     * @return string
     */
    public function getProductEntity($product)
    {
        if (!Config::$settings['multivendor'])
            return '';

        $entity = Config::getEntityFromProduct($product) ?: Registrar::executeHook('actionGetMobbexProductEntity', false, $product);

        return $entity ?: '';
    }

    /**
     * Return payment data from a cart, this additional information is for the invoice pdf
     * 
     * @param int $id_cart
     * @return string
     */
    public function getInvoiceData($id_cart)
    {

        $tab = '<table style="border: solid 1pt black; padding:0 10pt">';

        // Get Transaction Data
        $transactions = Transaction::getTransactions($id_cart);

        // Check if data exists
        if (empty($transactions) || !is_array($transactions)) {
            return false;
        }

        foreach ($transactions as $trx) {

            $trxData = json_decode($trx->data, true);

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
     * Duplicate a Cart instance and save it to context.
     * 
     * @param \Cart $cart
     * 
     * @return \Cart|null New Cart.
     */
    public function restoreCart($cart)
    {
        $result = $cart->duplicate();

        if (!$result || !\Validate::isLoadedObject($result['cart']) || !$result['success'])
        return Logger::log('error', 'Helper > createCheckout | Error Creating/Loading Order On Order Process', ['cart id' => isset($cart->id) ? $cart->id : 0]);

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
    public function getShippingCost($cart)
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
    public function getRoundedTotal($cart)
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

    /**
     * Get a product id from him reference.
     * 
     * @param string $reference
     * 
     * @return int
     */
    public static function getProductIdByReference($reference)
    {
        return (int) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            "SELECT `id_product` FROM " . _DB_PREFIX_ . "product WHERE `reference` = '$reference'"
        );
    }

    /**
     * filterFeaturedPlans get the featured plans configured for a product
     * 
     * @param array $sources
     * @param array $featuredPlans
     * 
     * @return string best plan
     */
    public function filterFeaturedPlans($sources, $featuredPlans)
    {
        $featuredInstallments = [];

        foreach ($sources as $source) {
            if (empty($source['installments']) || empty($source['installments']['enabled'])) {
                continue;
            }

            $installment_list = isset($source['installments']['list']) && is_array($source['installments']['list'])
                ? $source['installments']['list']
                : [];

            foreach ($installment_list as $i) {
                if (empty($i['uid'])) {
                    continue;
                }

                if (in_array($i['uid'], $featuredPlans, true) || in_array($i['reference'], $featuredPlans, true)) {
                    $featuredInstallments[] = [
                        'count'      => isset($i['totals']['installment']['count']) ? $i['totals']['installment']['count'] : null,
                        'amount'     => isset($i['totals']['installment']['amount']) ? $i['totals']['installment']['amount'] : null,
                        'source'     => isset($source['source']['name']) ? $source['source']['name'] : 'Unknown',
                        'percentage' => isset($i['totals']['financial']['percentage']) ? $i['totals']['financial']['percentage'] : 0,
                    ];
                }
            }
        }

        if (empty($featuredInstallments))
            return null;

        return $this->setBestPlan($featuredInstallments);
    }


    /**
     * setBestPlan evaluates between featured installments to get the best one
     * 
     * @param array $featuredInstallments
     * 
     * @return string $bestPlan
     */
    private function setBestPlan($featuredInstallments)
    {
        $bestPlan = null;

        foreach ($featuredInstallments as $plan) {
            if ($bestPlan === null) {
                $bestPlan = $plan;
                continue;
            }

            $currentDiscount = isset($plan['percentage']) ? $plan['percentage'] : 0;
            $bestDiscount    = isset($bestPlan['percentage']) ? $bestPlan['percentage'] : 0;

            if ($currentDiscount < $bestDiscount)
                $bestPlan = $plan;
            elseif ($currentDiscount == $bestDiscount && $plan['count'] > $bestPlan['count'])
                $bestPlan = $plan;
        }

        return json_encode($bestPlan);
    }
}