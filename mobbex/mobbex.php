<?php

/**
 * mobbex.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 3.5.2
 * @see     PaymentModuleCore
 */

if (!defined('_PS_VERSION_'))
    exit;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Main class of the module
 */
class Mobbex extends PaymentModule
{
    /** @var \Mobbex\PS\Checkout\Models\Config */
    public $config;

    /** @var \Mobbex\PS\Checkout\Models\Updater */
    public $updater;

    /** @var \Mobbex\PS\Checkout\Models\Registrar */
    public $registrar;

    /** @var \Mobbex\PS\Checkout\Models\OrderHelper */
    public $helper;

    /** @var \Mobbex\PS\Checkout\Models\Logger */
    public $logger;

    /** @var \Mobbex\PS\Checkout\Models\Installer */
    public $installer;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name            = 'mobbex';
        $this->tab             = 'payments_gateways';
        $this->version         = \Mobbex\PS\Checkout\Models\Config::MODULE_VERSION;
        $this->author          = 'Mobbex Co';
        $this->controllers     = ['notification', 'payment', 'task', 'sources'];
        $this->currencies      = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap       = true;

        parent::__construct();

        $this->displayName            = $this->l('Mobbex');
        $this->description            = $this->l('Payment plugin using Mobbex ');
        $this->confirmUninstall       = $this->l('Are you sure you want to uninstall?');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->smarty                 = \Context::getContext()->smarty;

        //Mobbex Classes 
        $this->config    = new \Mobbex\PS\Checkout\Models\Config();
        $this->registrar = new \Mobbex\PS\Checkout\Models\Registrar();
        $this->helper    = new \Mobbex\PS\Checkout\Models\OrderHelper();
        $this->logger    = new \Mobbex\PS\Checkout\Models\Logger();
        $this->updater   = new \Mobbex\PS\Checkout\Models\Updater();
        $this->installer = new \Mobbex\PS\Checkout\Models\Installer();
        
        //Init php sdk
        $this->initSdk();

        // On 1.7.5 ignores the creation and finishes on an Fatal Error
        // Create the States if not exists because are really important
        if ($this::isEnabled($this->name))
            $this->installer->createStates($this->config->orderStatuses);

        // Only if you want to publish your module on the Addons Marketplace
        $this->module_key = 'mobbex_checkout';

        // Execute pending tasks if cron is disabled
        if ($this->active && !defined('mobbexTasksExecuted') && !$this->config->settings['cron_mode'] && !\Mobbex\PS\Checkout\Models\Updater::needUpgrade())
            define('mobbexTasksExecuted', true) && \Mobbex\PS\Checkout\Models\Task::executePendingTasks();
    }

    public function isUsingNewTranslationSystem()
    {
        return false;
    }

    /**
     * Install the module on the store
     *
     * @see    Module::install()
     * @todo   bootstrap the configuration requirements of Mobbex
     * @throws PrestaShopException
     * @return bool
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension ') . $this->l('on your server to install this module.');

            return false;
        }

        return parent::install()
            && $this->installer->createTables()
            && $this->installer->createStates($this->config->orderStatuses)
            && $this->installer->createCostProduct()
            && $this->registrar->unregisterHooks($this)
            && $this->registrar->registerHooks($this)
            && $this->registrar->addExtensionHooks();
    }

    /**
     * Uninstall the module
     *
     * @see    Module::uninstall()
     * @todo   remove the configuration requirements of Mobbex
     * @throws PrestaShopException
     * @return bool
     */
    public function uninstall()
    {
        // Delete module config if option is sent
        if (isset($_COOKIE['mbbx_remove_config']) && $_COOKIE['mbbx_remove_config'] === 'true')
            $this->config->deleteSettings();

        $this->registrar->unregisterHooks($this);

        return parent::uninstall();
    }

    /**
     * Init the PHP Sdk and configure it with module & plataform data.
     */
    public function initSdk()
    {
        // Set platform information
        \Mobbex\Platform::init(
            'Prestashop' . _PS_VERSION_,
            \Mobbex\PS\Checkout\Models\Config::MODULE_VERSION,
            \Tools::getShopDomainSsl(true, true),
            [
                'Prestashop' => _PS_VERSION_,
                'webpay'     => \Mobbex\PS\Checkout\Models\Config::MODULE_VERSION,
                'sdk'        => class_exists('\Composer\InstalledVersions') && \Composer\InstalledVersions::isInstalled('mobbexco/php-plugins-sdk') ? \Composer\InstalledVersions::getVersion('mobbexco/php-plugins-sdk') : '',
            ],
            $this->config->settings,
            [$this->registrar, 'executeHook']
        );

        // Init api conector
        \Mobbex\Api::init();
    }

    /**
     * Entry point to the module configuration page
     *
     * @see Module::getContent()
     * @return string
     */
    public function getContent()
    {
        if (Tools::isSubmit('submit_mobbex')) {
            $this->postProcess();
        }

        if (!empty($_GET['run_update'])) {
            $this->runUpdate();
            Tools::redirectAdmin(\Mobbex\PS\Checkout\Models\Updater::getUpgradeURL());
        }

        $this->context->smarty->assign(array('module_dir' => $this->_path));

        return $this->renderForm();
    }

    /**
     * Generate the configuration form HTML markup
     *
     * @return string
     */
    protected function renderForm()
    {
        $helper = new \HelperForm();

        $helper->show_toolbar = false;
        $helper->table        = $this->table;
        $helper->module       = $this;
        $helper->default_form_language    = $this->context->language->id;
        $helper->allow_employee_form_lang = \Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier    = $this->identifier;
        $helper->submit_action = 'submit_mobbex';
        $helper->currentIndex  = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $form = $this->config->getConfigForm();

        try {
            if (\Mobbex\PS\Checkout\Models\Updater::needUpgrade())
                $form['form']['warning'] = 'Actualice la base de datos desde <a href="' . \Mobbex\PS\Checkout\Models\Updater::getUpgradeURL() . '">aquí</a> para que el módulo funcione correctamente.';

            if ($this->updater->hasUpdates(\Mobbex\PS\Checkout\Models\Config::MODULE_VERSION))
                $form['form']['description'] = "¡Nueva actualización disponible! Haga <a href='$_SERVER[REQUEST_URI]&run_update=1'>clic aquí</a> para actualizar a la versión " . $this->updater->latestRelease['tag_name'];
        } catch (\Exception $e) {
            $this->logger->log('error', 'Mobbex > renderForm | Error Obtaining Update/Upgrade Messages', $e->getMessage());
        }

        $helper->tpl_vars = array(
            'fields_value' => $this->config->getSettings('name'),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        );

        return $helper->generateForm([$form]);
    }

    /**
     * Logic to apply when the configuration form is posted
     *
     * @return void
     */
    public function postProcess()
    {
        foreach ($this->config->getSettings('name') as $key => $value)
            Configuration::updateValue($key, Tools::getValue($key));
    }

    /**
     * Try to update the module.
     * 
     * @return void 
     */
    public function runUpdate()
    {
        try {
            $this->updater->updateVersion($this, true);
        } catch (\PrestaShopException $e) {
            PrestaShopLogger::addLog('Mobbex Update Error: ' . $e->getMessage(), 3, null, 'Mobbex', null, true, null);
        }
    }

    /** HOOKS **/

    /** ACTION HOOKS **/

    /**
     * Logic to execute when the hook 'paymentOptions' is fired.
     * Creates the Mobbex Payment Options.
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart']) || !$this->helper->isPaymentStep())
            return;

        $options = [];
        $checkoutData = $this->helper->getPaymentData(false);

        // Get cards and payment methods
        $cards   = isset($checkoutData['wallet']) ? $checkoutData['wallet'] : [];
        $methods = isset($checkoutData['paymentMethods']) ? $checkoutData['paymentMethods'] : [];

        \Mobbex\PS\Checkout\Models\OrderHelper::addJavascriptData([
            'paymentUrl'  => \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('payment', 'process'),
            'errorUrl'    => \Mobbex\PS\Checkout\Models\OrderHelper::getUrl('index.php?controller=order&step=3&typeReturn=failure'),
            'embed'       => (bool) $this->config->settings['embed'],
            'data'        => $checkoutData,
            'return'      => \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('notification', 'return', '&id_cart=' . $params['cart']->id . '&status=' . 500),
        ]);

        // Get payment methods from checkout
        if ($this->config->settings['unified_method'] || isset($checkoutData['sid'])) {
            $options[] = $this->createPaymentOption(
                $this->config->settings['mobbex_title'] ?: $this->l('Paying using cards, cash or others'),
                $this->config->settings['mobbex_description'],
                \Media::getMediaPath(_PS_MODULE_DIR_ . 'mobbex/views/img/logo_transparent.png'),
                'module:mobbex/views/templates/front/payment.tpl',
                ['checkoutUrl' => \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('payment', 'redirect', "&id=$checkoutData[id]")]
            );
        } else {
            foreach ($methods as $method) {
                $checkoutUrl = \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('payment', 'redirect', "&id=$checkoutData[id]&method=$method[group]:$method[subgroup]");

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
            if($card['installments']) {
                $options[] = $this->createPaymentOption(
                    $card['name'],
                    null,
                    $card['source']['card']['product']['logo'],
                    'module:mobbex/views/templates/front/card-form.tpl',
                    compact('card', 'key')
                );
            }
        }

        $this->logger->log('debug', 'Observer > hookPaymentOptions', $options);

        return $options;
    }

    public function hookActionOrderReturn($params)
    {
        $order = new \Order($params['orderReturn']->orderId);

        if ($order->module != 'mobbex')
            return true;

        $trans  = \Mobbex\PS\Checkout\Models\Transaction::getTransactions($order->id_cart, true);

        try {
            // Process refund
            $result = \Mobbex\Api::request([
                'uri'    => 'https://api.mobbex.com/p/operations/' . $trans['payment_id'] . '/refund',
                'method' => 'POST',
                'body'   => ['total' => $order->getTotalPaid()],
            ]);

            // Update order status
            if ($result) {
                $order->setCurrentState((int) \Configuration::get('PS_OS_REFUND'));
                $order->save();
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->log('error', 'mobbex > hookActionOrderReturn |', $e->getMessage());
            return false;
        }
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
            ->setValue(isset($customer->id) ? $this->helper->getDni($customer->id) : '')
            ->setType('text')
            ->setRequired(true)
            ->setLabel($this->l('DNI'));

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
     * Update category options (ps 1.6 only).
     */
    public function hookActionCategoryAdd($params)
    {
        $this->hookActionCategoryUpdate($params);
    }

    /**
     * Update category options (ps 1.6 only).
     */
    public function hookActionCategoryUpdate($params)
    {
        $this->saveCatalogOptions(
            isset($params['category']->id) ? $params['category']->id : \Tools::getValue('id_category'),
            'category'
        );
    }

    /**
     * Executes when hook ActionAfterCreateCategoryFormHandler is fired. (Used to update category options).
     */
    public function hookActionAfterCreateCategoryFormHandler($params)
    {
        $this->saveCatalogOptions(
            !empty($params['id']) ? $params['id'] : \Tools::getValue('id_category'),
            'category'
        );
    }

    /**
     * Executes when hook ActionAfterUpdateCategoryFormHandler is fired. (Used to update category options).
     */
    public function hookActionAfterUpdateCategoryFormHandler($params)
    {
        $this->saveCatalogOptions(
            !empty($params['id']) ? $params['id'] : \Tools::getValue('id_category'),
            'category'
        );
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
            $this->helper->addAsset("$mediaPath/views/js/uninstall-options.js");

        // Configuration page
        if ($currentPage == 'AdminModules' && \Tools::getValue('configure') == 'mobbex') {
            $this->helper->addAsset("$mediaPath/views/js/mobbex-config.js");

            try {
                // If plugin has updates, add update data to javascript
                if ($this->updater->hasUpdates(\Mobbex\PS\Checkout\Models\Config::MODULE_VERSION))
                    \Mobbex\PS\Checkout\Models\OrderHelper::addJavascriptData(['updateVersion' => $this->updater->latestRelease['tag_name']]);
            } catch (\Exception $e) {
                $this->logger->log('fatal', 'Observer > hookDisplayBackOfficeHeader | Error Obtaining Update/Upgrade Messages', $e->getMessage());
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
        if ($params['template'] != 'order_conf' || empty($params['templateVars']['{order_name}']))
            return true;

        // Intance order from reference
        $order = \Order::getByReference($params['templateVars']['{order_name}'])->getFirst();

        // Only check status on mobbex orders
        if (!$order || $order->module != 'mobbex')
            return true;

        // Allow mails of approved payments
        return $order->getCurrentState() == (\Configuration::get('MOBBEX_ORDER_STATUS_APPROVED') ?: \Configuration::get('PS_OS_PAYMENT'));
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
            $this->helper->addAsset("$mediaPath/views/css/front.css", 'css');
            $this->helper->addAsset("$mediaPath/views/js/front.js");

            if ($this->config->settings['wallet'])
                $this->helper->addAsset('https://res.mobbex.com/js/sdk/mobbex@1.1.0.js');

            if ($this->config->settings['embed'])
                $this->helper->addAsset('https://res.mobbex.com/js/embed/mobbex.embed@1.0.23.js');
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
        $tab = $this->helper->getInvoiceData($params['object']->id_order);

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
            $trx          = \Mobbex\PS\Checkout\Models\Transaction::getTransactions($order->id_cart, true);
            $sources      = \Mobbex\PS\Checkout\Models\Transaction::getTransactionsSources($trx, !empty($transactions) ? $transactions : $trx->getChilds());

            // Assign the Data into Smarty
            $this->smarty->assign('status', $order->getCurrentStateFull(\Context::getContext()->language->id)['name']);
            $this->smarty->assign('total', $trx->total);
            $this->smarty->assign('payment', $order->payment);
            $this->smarty->assign('status_message', $trx->status_message);
            $this->smarty->assign('sources', $sources);
        }

        return $this->display(__FILE__, 'views/templates/hooks/orderconfirmation.tpl');
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
        $checkoutData = $this->helper->getPaymentData(false);

        // Make sure the assets are loaded correctly
        $this->hookDisplayHeader(true);

        // Add payment information to js
        \Media::addJsDef([
            'mbbx' => [
                'paymentUrl' => \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('payment', 'process'),
                'errorUrl'   => \Mobbex\PS\Checkout\Models\OrderHelper::getUrl('index.php?controller=order&step=3&typeReturn=failure'),
                'embed'      => (bool) $this->config->settings['embed'],
                'data'       => $checkoutData,
                'return'     => \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('notification', 'return', '&id_cart=' . \Context::getContext()->cookie->__get('last_cart') . '&status=' . 500)
            ]
        ]);

        $this->smarty->assign([
            'methods'     => isset($checkoutData['paymentMethods']) && !$this->config->settings['unified_method'] ? $checkoutData['paymentMethods'] : [],
            'cards'       => isset($checkoutData['wallet']) ? $checkoutData['wallet'] : [],
            'redirectUrl' => isset($checkoutData['id']) ? \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('payment', 'redirect', "&id=$checkoutData[id]") : '',
        ]);

        return $this->display(__FILE__, 'views/templates/front/payment.tpl');
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
        if (!$this->config->settings['mobbex_dni'] || $this->config->settings['custom_dni'] != '')
            return;

        $customer = \Context::getContext()->customer;

        $this->smarty->assign(
            array(
                'last_dni' => isset($customer->id) ? $this->helper->getDni($customer->id) : "",
            )
        );

        return $this->display(__FILE__, 'views/templates/hooks/dnifield.tpl');
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
        $order  = new \Order($params['id_order']);

        //Get transaction data
        $parent = \Mobbex\PS\Checkout\Models\Transaction::getTransactions($order->id_cart, true);
        $childs = !empty($parent->childs) ? $parent->getChilds() : $parent->loadChildTransactions();

        if (!$parent)
            return;

        $this->smarty->assign(
            [
                'id' => $parent->payment_id,
                'cart_id'  => $params['id_order'],
                'data' => [
                    'payment_id'     => $parent->payment_id,
                    'risk_analysis'  => $parent->risk_analysis,
                    'currency'       => $parent->currency,
                    'total'          => $parent->total,
                    'status_message' => $parent->status_message,
                ],
                'sources'  => \Mobbex\PS\Checkout\Models\Transaction::getTransactionsSources($parent, $childs),
                'entities' => \Mobbex\PS\Checkout\Models\Transaction::getTransactionsEntities($parent, $childs),
                'coupon'   => \Mobbex\PS\Checkout\Models\Transaction::generateCoupon($parent),
            ]
        );

        return $this->display(__FILE__, 'views/templates/hooks/order-widget.tpl');
    }

    /** UTIL METHODS REPOSITORY **/

    /**
     * Display finance widget.
     * 
     * @param float|int|string $total Amount to calculate sources.
     * @param array|null $products
     */
    public function displayPlansWidget($total, $products = [])
    {
        extract($this->config->getProductPlans($products));

        $data = [
            'product_price'  => \Product::convertAndFormatPrice($total),
            'sources'        => \Mobbex\Repository::getSources($total, \Mobbex\Repository::getInstallments($products, $common_plans, $advanced_plans)),
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

        return $this->display(__FILE__, 'views/templates/finance-widget/local.tpl');
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
        extract($this->config->getProductPlans([$id], $catalogType, true));

        $options  = [
            'id'             => $id,
            'update_sources' => \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('sources', 'update', "&hash=$hash"),
            'plans'          => $this->config->getStoredSources(),
            'check_common'   => $common_plans,
            'check_advanced' => $advanced_plans,
            'entity'         => \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($id, $catalogType, 'entity') ?: '',
        ];

        if ($catalogType === 'product') {
            $options['subscription'] = [
                'uid'    => \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($id, 'product', 'subscription_uid') ?: '',
                'enable' => \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($id, 'product', 'subscription_enable') ?: 'no'
            ];
        }

        $this->smarty->assign($options);

        return $this->display(__FILE__, $template);
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
        if (!$this->config->settings['mobbex_dni'] || empty($params['object']->id) || empty($_POST['customer_dni']) || $this->config->settings['custom_dni'] != '')
            return;

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
