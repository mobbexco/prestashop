<?php

namespace Mobbex\PS\Checkout\Observers;

if (!defined('_PS_VERSION_'))
    exit;

class Observer
{

    public function __construct()
    {
        $this->config  = new \Mobbex\PS\Checkout\Models\Config();
        $this->updater = new \Mobbex\PS\Checkout\Models\Updater();
        $this->logger  = new \Mobbex\PS\Checkout\Models\Logger();
        $this->smarty  = \Context::getContext()->smarty;
        $this->active  = \Module::isEnabled('mobbex');
    }

    /** ACTION HOOKS **/

    /**
     * Logic to execute when the hook 'paymentOptions' is fired.
     * Creates the Mobbex Payment Options.
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart']) || !\Mobbex\PS\Checkout\Models\Helper::isPaymentStep())
            return;

        $options = [];
        $checkoutData = \Mobbex\PS\Checkout\Models\Helper::getPaymentData();

        // Get cards and payment methods
        $cards   = isset($checkoutData['wallet']) ? $checkoutData['wallet'] : [];
        $methods = isset($checkoutData['paymentMethods']) ? $checkoutData['paymentMethods'] : [];

        \Mobbex\PS\Checkout\Models\Helper::addJavascriptData([
            'paymentUrl'  => \Mobbex\PS\Checkout\Models\Helper::getModuleUrl('payment', 'process'),
            'errorUrl'    => \Mobbex\PS\Checkout\Models\Helper::getUrl('index.php?controller=order&step=3&typeReturn=failure'),
            'embed'       => (bool) $this->config->settings['embed'],
            'data'        => $checkoutData,
            'return'      => \Mobbex\PS\Checkout\Models\Helper::getModuleUrl('notification', 'return', '&id_cart=' . $params['cart']->id . '&status=' . 500),
        ]);

        // Get payment methods from checkout
        if ($this->config->settings['unified_method'] || isset($checkoutData['sid'])) {
            $options[] = $this->createPaymentOption(
                $this->config->settings['mobbex_title'] ?: $this->config->l('Paying using cards, cash or others'),
                $this->config->settings['mobbex_description'],
                \Media::getMediaPath(_PS_MODULE_DIR_ . 'mobbex/views/img/logo_transparent.png'),
                'module:mobbex/views/templates/front/payment.tpl',
                ['checkoutUrl' => \Mobbex\PS\Checkout\Models\Helper::getModuleUrl('payment', 'redirect', "&id=$checkoutData[id]")]
            );
        } else {
            foreach ($methods as $method) {
                $checkoutUrl = \Mobbex\PS\Checkout\Models\Helper::getModuleUrl('payment', 'redirect', "&id=$checkoutData[id]&method=$method[group]:$method[subgroup]");

                $options[] = $this->createPaymentOption(
                    (count($methods) == 1 || $method['subgroup'] == 'card_input') && $this->config->settings['mobbex_title'] ? $this->config->settings['mobbex_title'] : $method['subgroup_title'],
                    (count($methods) == 1 || $method['subgroup'] == 'card_input') ? $this->config->settings['mobbex_description'] : null,
                    $method['subgroup_logo'],
                    'module:mobbex/views/templates/front/method.tpl',
                    compact('method', 'checkoutUrl')
                );
            }
        }

        // Get wallet cards
        foreach ($cards as $key => $card) {
            $options[] = $this->createPaymentOption(
                $card['name'],
                null,
                $card['source']['card']['product']['logo'],
                'module:mobbex/views/templates/front/card-form.tpl',
                compact('card', 'key')
            );
        }

        $this->logger->log('debug', 'Observer > hookPaymentOptions', $options);

        return $options;
    }

    public function hookActionOrderReturn($params)
    {
        $order = new \Order($params['orderReturn']->orderId);

        if ($order->module != 'mobbex')
        return true;

        // Process refund
        $trans  = \Mobbex\PS\Checkout\Models\Transaction::getParentTransaction($order->id_cart);
        $result = \Mobbex\PS\Checkout\Models\Helper::processRefund($order->getTotalPaid(), $trans['payment_id']);

        // Update order status
        if ($result) {
            $order->setCurrentState((int) \Configuration::get('PS_OS_REFUND'));
            $order->save();
        }

        return $result;
    }

    /**
     * Logic to execute when the hook 'AdditionalCustomerFormFields' is fired.
     * Add Mobbex own fields to prestashop checkout.
     */
    public function hookAdditionalCustomerFormFields($params)
    {
        if (!$this->config->settings['mobbex_dni'] || $this->config->settings['custom_dni'] != '')
            return;

        $customer  = \Context::getContext()->customer;
        $dni_field = array();

        $dni_field['customer_dni'] = (new \FormField)
            ->setName('customer_dni')
            ->setValue(isset($customer->id) ? \Mobbex\PS\Checkout\Models\Helper::getDni($customer->id) : '')
            ->setType('text')
            ->setRequired(true)
            ->setLabel($this->config->l('DNI'));

        return $dni_field;
    }
    
    public function hookActionObjectCustomerUpdateAfter(array $params)
    {
        $this->updateCustomerDniStatus($params);
    }

    public function hookActionObjectCustomerAddAfter(array $params)
    {
        $this->updateCustomerDniStatus($params);
    }

    /**
     * Create costumer hook for Prestashop 1.6
     *
     * Support for 1.6 Only
     *
     * @return string
     */
    public function hookActionCustomerAccountAdd()
    {
        $customer         = \Context::getContext()->customer;
        $params['object'] = isset($customer->id) ? $customer : "";

        $this->updateCustomerDniStatus($params);
    }

    /**
     * Executes when hook ActionAdminProductsControllerSaveBefore is fired. (Used to update product options).
     */
    public function hookActionAdminProductsControllerSaveBefore()
    {
        $this->saveCatalogOptions($_POST['id_product']);
    }

    /**
     * Executes when hook ActionCategoryUpdate is fired. (Used to update category options).
     */
    public function hookActionCategoryUpdate()
    {
        $this->hookActionAfterUpdateCategoryFormHandler();
    }

    /**
     * Executes when hook ActionAfterUpdateCategoryFormHandler is fired. (Used to update category options).
     */
    public function hookActionAfterUpdateCategoryFormHandler()
    {
        $id = !empty($params['request']) ? $params['request']->get('categoryId') : \Tools::getValue('id_category');
        $this->saveCatalogOptions($id, 'category');
    }

    /**
     * Load back office scripts.
     * 
     * @return void
     */
    public function hookDisplayBackOfficeHeader()
    {
        $currentPage = \Tools::getValue('controller');
        $mediaPath   = \Media::getMediaPath(_PS_MODULE_DIR_ . 'mobbex');

        // Module Manager page
        if ($currentPage == 'AdminModulesManage')
            \Mobbex\PS\Checkout\Models\Helper::addAsset("$mediaPath/views/js/uninstall-options.js");

        // Configuration page
        if ($currentPage == 'AdminModules' && \Tools::getValue('configure') == 'mobbex') {
            \Mobbex\PS\Checkout\Models\Helper::addAsset("$mediaPath/views/js/mobbex-config.js");

            try {
                // If plugin has updates, add update data to javascript
                if ($this->updater->hasUpdates(\Mobbex\PS\Checkout\Models\Config::MODULE_VERSION))
                    \Mobbex\PS\Checkout\Models\Helper::addJavascriptData(['updateVersion' => $this->updater->latestRelease['tag_name']]);
            } catch (\Exception $e) {
                $this->logger->log('fatal','Observer > hookDisplayBackOfficeHeader | Error Obtaining Update/Upgrade Messages', $e->getMessage());
            }
        }
    }

    /**
     * Support displayHeader hook aliases.
     */
    public function hookHeader()
    {
        return $this->hookDisplayHeader();
    }

    public function hookActionEmailSendBefore($params)
    {

        if ($params['template'] == 'order_conf' && !empty($params['templateVars']['id_order'])) {
            $order = new \Order($params['templateVars']['id_order']);

            // If current order state is not approved, block mail sending
            if ($order->getCurrentState() != \Configuration::get('PS_OS_PAYMENT'))
                return false;
        }
    }

    public function hookActionMobbexExpireOrder($orderId)
    {
        $order = new \Order($orderId);

        // Exit if order cannot be loaded correctly
        if (!$order)
            return false;

        if ($order->getCurrentState() == \Configuration::get('MOBBEX_OS_PENDING'))
            $order->setCurrentState((int) \Configuration::get('PS_OS_CANCELED'));

        return true;
    }


    /** DISPLAY HOOKS **/

    /**
     * Support displayHeader hook aliases.
     */
    public function hookDisplayMobileHeader()
    {
        return $this->hookDisplayHeader();
    }

    /**
     * Load front end scripts.
     * 
     * @param bool $force Ignore page name check to load scripts.
     */
    public function hookDisplayHeader($force = false)
    {
        $currentPage = \Tools::getValue('controller');
        $mediaPath   = \Media::getMediaPath(_PS_MODULE_DIR_ . 'mobbex');

        // Checkout page
        if ($currentPage == 'order' || $force) {
            \Mobbex\PS\Checkout\Models\Helper::addAsset("$mediaPath/views/css/front.css", 'css');
            \Mobbex\PS\Checkout\Models\Helper::addAsset("$mediaPath/views/js/front.js");

            if ($this->config->settings['wallet'])
            \Mobbex\PS\Checkout\Models\Helper::addAsset('https://res.mobbex.com/js/sdk/mobbex@1.1.0.js');

            if ($this->config->settings['embed'])
            \Mobbex\PS\Checkout\Models\Helper::addAsset('https://res.mobbex.com/js/embed/mobbex.embed@1.0.20.js');
        }
    }

    /**
     * Add new information to the Invoice PDF
     * - Card Number
     * - Customer Name
     * - Customer ID
     * @param array $params
     * @return String
     */
    public function hookDisplayPDFInvoice($params)
    {
        $tab = \Mobbex\PS\Checkout\Models\Helper::getInvoiceData($params['object']->id_order);

        return $tab;
    }

    /**
     * Logic to execute when the hook 'displayPaymentReturn' is fired
     *
     * @return string
     */
    public function hookPaymentReturn($params)
    {

        if ($this->active == false)
            return;

        /** @var \Order $order */
        if (isset($params['order'])) {
            $order = $params['order'];
        } else {
            $order = $params['objOrder'];
        }

        if ($order) {
            // Get Transaction Data
            $transactions = \Mobbex\PS\Checkout\Models\Transaction::getTransactions($order->id_cart);
            $trx          = \Mobbex\PS\Checkout\Models\Transaction::getParentTransaction($order->id_cart);
            $sources      = \Mobbex\PS\Checkout\Models\Helper::getWebhookSources($transactions);

            // Assign the Data into Smarty
            $this->smarty->assign('status', $order->getCurrentStateFull(\Context::getContext()->language->id)['name']);
            $this->smarty->assign('total', $trx->total);
            $this->smarty->assign('payment', $order->payment);
            $this->smarty->assign('status_message', $trx->status_message);
            $this->smarty->assign('sources', $sources);
        }

        return $this->display('views/templates/hooks/orderconfirmation.tpl');
    }

    /**
     * Logic to execute when the hook 'displayProductPriceBlock' is fired.
     */
    public function hookDisplayProductPriceBlock($params)
    {

        if ($params['type'] !== 'after_price' || empty($params['product']) || empty($params['product']['show_price']) || !$this->config->settings['finance_product'])
            return;

        return $this->displayPlansWidget($params['product']['price_amount'], [$params['product']['id']]);
    }

    /**
     * Hook to display finance widget in cart page.
     * 
     * Support for 1.6 Only.
     * 
     * @return string|bool
     */
    public function hookDisplayShoppingCartFooter()
    {
        return $this->hookDisplayExpressCheckout();
    }

    /**
     * Hook to display finance widget in cart page.
     * 
     * @return string|bool
     */
    public function hookDisplayExpressCheckout()
    {
        $cart = \Context::getContext()->cart;

        if (!\Validate::isLoadedObject($cart) || !$this->config->settings['finance_cart'])
            return false;

        return $this->displayPlansWidget((float) $cart->getOrderTotal(true, \Cart::BOTH), array_column($cart->getProducts(), 'id_product'));
    }

    /**
     * Logic to execute when the hook 'displayPayment' is fired
     *
     * Support for 1.6 Only
     *
     * @return string
     */
    public function hookPayment()
    {
        $checkoutData = \Mobbex\PS\Checkout\Models\Helper::getPaymentData();

        // Make sure the assets are loaded correctly
        $this->hookDisplayHeader(true);

        // Add payment information to js
        \Media::addJsDef([
            'mbbx' => [
                'paymentUrl' => \Mobbex\PS\Checkout\Models\Helper::getModuleUrl('payment', 'process'),
                'errorUrl'   => \Mobbex\PS\Checkout\Models\Helper::getUrl('index.php?controller=order&step=3&typeReturn=failure'),
                'embed'      => (bool) $this->config->settings['embed'],
                'data'       => $checkoutData,
                'return'     => \Mobbex\PS\Checkout\Models\Helper::getModuleUrl('notification', 'return', '&id_cart=' . \Context::getContext()->cookie->__get('last_cart') . '&status=' . 500)
            ]
        ]);

        $this->smarty->assign([
            'methods'     => isset($checkoutData['paymentMethods']) && !$this->config->settings['unified_method'] ? $checkoutData['paymentMethods'] : [],
            'cards'       => isset($checkoutData['wallet']) ? $checkoutData['wallet'] : [],
            'redirectUrl' => isset($checkoutData['id']) ? \Mobbex\PS\Checkout\Models\Helper::getModuleUrl('payment', 'redirect', "&id=$checkoutData[id]") : '',
        ]);

        return $this->display('views/templates/front/payment.tpl');
    }

    /**
     * Plans widget hook for Prestashop 1.6
     *
     * Support for 1.6 Only
     *
     * @return string
     */
    public function hookDisplayProductButtons()
    {
        $product = new \Product(\Tools::getValue('id_product'));

        if (!$this->config->settings['finance_product'] || !\Validate::isLoadedObject($product) || !$product->show_price)
            return;

        return $this->displayPlansWidget($product->getPrice(), [$product]);
    }

    /**
     * Display DNI field hook for Prestashop 1.6
     *
     * Support for 1.6 Only
     *
     * @return string
     */
    public function hookDisplayCustomerAccountForm()
    {
        if ($this->config->settings['mobbex_dni'] || $this->config->settings['custom_dni'] != '')
            return;

        $customer = \Context::getContext()->customer;

        $this->smarty->assign(
            array(
                'last_dni' => isset($customer->id) ? \Mobbex\PS\Checkout\Models\Helper::getDni($customer->id) : "",
            )
        );

        return $this->display('views/templates/hooks/dnifield.tpl');
    }

    /**
     * Show product admin settings.
     * 
     * @param array $params
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        $id   = !empty($params['id_product']) ? $params['id_product'] : \Tools::getValue('id_product');
        return $this->displayCatalogOptions($id);
    }

    /**
     * Show category admin settings.
     * 
     * @param array $params
     */
    public function hookDisplayBackOfficeCategory($params)
    {

        $id = !empty($params['request']) ? $params['request']->get('categoryId') : \Tools::getValue('id_category');
        return $this->displayCatalogOptions($id, 'category');
    }

    /**
     * Show the mobbex order widget in the order panel backoffice.
     * 
     * @param array $params
     */
    public function hookDisplayAdminOrder($params)
    {

        $order        = new \Order($params['id_order']);
        $trx          = \Mobbex\PS\Checkout\Models\Transaction::getParentTransaction($order->id_cart);
        $transactions = \Mobbex\PS\Checkout\Models\Transaction::getTransactions($order->id_cart);

        if (!$trx)
            return;

        $this->smarty->assign(
            [
                'id' => $trx->payment_id,
                'data' => [
                    'payment_id'     => $trx->payment_id,
                    'risk_analysis'  => $trx->risk_analysis,
                    'currency'       => $trx->currency,
                    'total'          => $trx->total,
                    'status_message' => $trx->status_message,
                ],
                'sources'  => \Mobbex\PS\Checkout\Models\Helper::getWebhookSources($transactions),
                'entities' => \Mobbex\PS\Checkout\Models\Helper::getWebhookEntities($transactions)
            ]
        );

        return $this->display('views/templates/hooks/order-widget.tpl');
    }

    /** UTIL METHODS REPOSITORY **/

    /**
     * Display a given Template.
     * @param string $template
     */
    private function display($template)
    {
        $template = _PS_MODULE_DIR_ . "mobbex/$template";

        return $this->smarty->fetch($template);
    }

    /**
     * Display finance widget.
     * 
     * @param float|int|string $total Amount to calculate sources.
     * @param array|null $products
     */
    public function displayPlansWidget($total, $products = [])
    {
        $data = [
            'product_price'  => \Product::convertAndFormatPrice($total),
            'sources'        => \Mobbex\PS\Checkout\Models\Helper::getSources($total, \Mobbex\PS\Checkout\Models\Helper::getInstallments($products)),
            'style_settings' => [
                'default_styles' => \Tools::getValue('controller') == 'cart' || \Tools::getValue('controller') == 'order',
                'styles'         => $this->config->settings['widget_styles'] ?: $this->config->default['widget_styles'],
                'text'           => $this->config->settings['widget_text'] ?: $this->config->default['widget_text'],
                'button_image'   => $this->config->settings['widget_logo'],
                'plans_theme'    => $this->config->settings['theme'] ?: $this->config->default['theme'],
            ],
        ];
        //Debug Data
        $this->logger->log('debug', 'Observer > displayPlansWidget', $data);
        
        //Assign data to template
        $this->smarty->assign($data);

        return $this->display('views/templates/finance-widget/local.tpl');
    }

    /**
     * Display Mobbex options in catalog config.
     * @param string $id
     * @param string $catalogType
     */
    private function displayCatalogOptions($id, $catalogType = 'product')
    {
        $hash     = md5($this->config->settings['api_key'] . '!' . $this->config->settings['access_token']);
        $template = "views/templates/hooks/$catalogType-settings.tpl";
        $options  = [
            'id'             => $id,
            'update_sources' => \Mobbex\PS\Checkout\Models\Helper::getModuleUrl('sources', 'update', "&hash=$hash"),
            'plans'          => \Mobbex\PS\Checkout\Models\Helper::getPlansFilterFields($id, $catalogType),
            'entity'         => \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($id, $catalogType, 'entity') ?: '',
        ];

        if ($catalogType === 'product') {
            $options['subscription'] = [
                'uid'    => \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($id, 'product', 'subscription_uid') ?: '',
                'enable' => \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($id, 'product', 'subscription_enable') ?: 'no'
            ];
        }

        $this->smarty->assign($options);

        return $this->display($template);
    }

    /**
     * Save the Mobbex Config for catalog options in database.
     * @param string $id
     * @param string $catalogType
     */
    private function saveCatalogOptions($id, $catalogType = 'product')
    {
        $options = [
            'entity'         => isset($_REQUEST['entity']) ? $_REQUEST['entity'] : null,
            'common_plans'   => [],
            'advanced_plans' => []
        ];

        foreach ($_REQUEST as $key => $value) {
            if (strpos($key, 'common_plan_') !== false && $value === 'no') {
                // Add UID to common plans
                $options['common_plans'][] = explode('common_plan_', $key)[1];
            } else if (strpos($key, 'advanced_plan_') !== false && $value === 'yes') {
                // Add UID to advanced plans
                $options['advanced_plans'][] = explode('advanced_plan_', $key)[1];
            }
        }

        if ($catalogType === 'product') {
            $options['subscription_enable'] = isset($_REQUEST['sub_enable']) ? $_REQUEST['sub_enable'] : 'no';
            $options['subscription_uid']    = isset($_REQUEST['sub_uid'])    ? $_REQUEST['sub_uid']    : '';
        }

        foreach ($options as $key => $value)
            \Mobbex\PS\Checkout\Models\CustomFields::saveCustomField($id, $catalogType, $key, strpos($key, 'plans') ? json_encode($value) : $value);
    }

    /**
     * @param array $params
     */
    private function updateCustomerDniStatus(array $params)
    {
        if ($this->config->settings['mobbex_dni'] || empty($params['object']->id) || empty($_POST['customer_dni']) || $this->config->settings['custom_dni'] != '') {
            return;
        }

        $customer_id  = $params['object']->id;
        $customer_dni = $_POST['customer_dni'];

        return \Mobbex\PS\Checkout\Models\CustomFields::saveCustomField($customer_id, 'customer', 'dni', $customer_dni);
    }

    /**
     * Check if order currency is avaible in Mobbex.
     * @param Object
     * @return bool
     */
    private function checkCurrency($cart)
    {
        $currency_order    = new \Currency($cart->id_currency);
        $currencies_module = \Currency::getPaymentCurrencies(\Module::getModuleIdByName('mobbex'));

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency'])
                    return true;
            }
        }

        return false;
    }

    /**
     * Creates payment option for Prestashop Checkout.
     * @param string $title
     * @param string $description
     * @param string $logo
     * @param string $template
     * @param array $templateVars
     * 
     * @return Object
     */
    private function createPaymentOption($title, $description, $logo, $template, $templateVars = null)
    {
        if ($templateVars)
            $this->smarty->assign($templateVars);

        $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($title)
            ->setForm($this->smarty->fetch($template))
            ->setLogo($logo)
            ->setAdditionalInformation($description ? "<section><p>$description</p></section>" : '');

        return $option;
    }
}
