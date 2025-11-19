<?php

/**
 * mobbex.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 4.5.1
 * @see     PaymentModuleCore
 */

defined('_PS_VERSION_') || exit;

require_once __DIR__ . '/vendor/autoload.php';

use Mobbex\PS\Checkout\Models\Config;
use Mobbex\PS\Checkout\Models\Logger;
use Mobbex\PS\Checkout\Models\CustomFields;

/**
 * Main class of the module
 */
class Mobbex extends PaymentModule
{
    /** @var \Mobbex\PS\Checkout\Models\Updater */
    public $updater;

    /** @var \Mobbex\PS\Checkout\Models\Registrar */
    public $registrar;

    /** @var \Mobbex\PS\Checkout\Models\OrderHelper */
    public $helper;

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
        $this->controllers     = ['notification', 'payment', 'task', 'sources', 'capture'];
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
        \Mobbex\PS\Checkout\Models\Config::init();
        $this->registrar = new \Mobbex\PS\Checkout\Models\Registrar();
        $this->helper    = new \Mobbex\PS\Checkout\Models\OrderHelper();
        $this->updater   = new \Mobbex\PS\Checkout\Models\Updater();
        $this->installer = new \Mobbex\PS\Checkout\Models\Installer();
        $this->cache     = new \Mobbex\PS\Checkout\Models\Cache();
        
        //Init php sdk
        $this->initSdk();

        // On 1.7.5 ignores the creation and finishes on an Fatal Error
        // Create the States if not exists because are really important
        if ($this::isEnabled($this->name))
            $this->installer->createStates(Config::$orderStatuses);

        // Only if you want to publish your module on the Addons Marketplace
        $this->module_key = 'mobbex_checkout';

        // Execute pending tasks if cron is disabled
        if ($this->active && !defined('mobbexTasksExecuted') && !Config::$settings['cron_mode'] && !\Mobbex\PS\Checkout\Models\Updater::needUpgrade())
            define('mobbexTasksExecuted', true) && \Mobbex\PS\Checkout\Models\Task::executePendingTasks();
    }

    /**
     * Add an error to the module errors array.
     * 
     * @param string $message The error message to add.
     */
    public function addError($message)
    {
        $this->_errors[] = $message;
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
        \Mobbex\PS\Checkout\Models\Logger::log('debug', 'Starting installation process');

        try {
            if (!extension_loaded('curl'))
                throw new \Exception('cURL extension is not enabled');

            if (!parent::install())
                throw new \Exception('Parent install failed');

            if (!$this->installer->createTables())
                throw new \Exception('Create tables failed');

            if (!$this->installer->createStates(Config::$orderStatuses))
                throw new \Exception('Create states failed');

            if (!$this->installer->createCostProduct())
                throw new \Exception('Create cost product failed');

            if (!$this->registrar->unregisterHooks($this))
                throw new \Exception('Unregister hooks failed');

            if (!$this->registrar->registerHooks($this))
                throw new \Exception('Register hooks failed');

            if (!$this->registrar->addExtensionHooks())
                throw new \Exception('Add extension hooks failed');

            return true;
        } catch (\Exception $e) {
            \Mobbex\PS\Checkout\Models\Logger::log('error', 'Install ' . $e->getMessage());
            $this->addError($e->getMessage());

            return false;
        }
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
            Config::deleteSettings();

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
            Config::$settings,
            [$this->registrar, 'executeHook'],
            ['\Mobbex\PS\Checkout\Models\Logger', 'log']
        );

        \Mobbex\Platform::loadModels($this->cache, new \Mobbex\PS\Checkout\Models\Db(_DB_PREFIX_));

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

        $form = Config::getConfigForm();

        try {
            if (\Mobbex\PS\Checkout\Models\Updater::needUpgrade())
                $form['form']['warning'] = 'Actualice la base de datos desde <a href="' . \Mobbex\PS\Checkout\Models\Updater::getUpgradeURL() . '">aquí</a> para que el módulo funcione correctamente.';

            if ($this->updater->hasUpdates(\Mobbex\PS\Checkout\Models\Config::MODULE_VERSION))
                $form['form']['description'] = "¡Nueva actualización disponible! Haga <a href='$_SERVER[REQUEST_URI]&run_update=1'>clic aquí</a> para actualizar a la versión " . $this->updater->latestRelease['tag_name'];
        } catch (\Exception $e) {
            Logger::log('error', 'Mobbex > renderForm | Error Obtaining Update/Upgrade Messages', $e->getMessage());
        }

        $helper->tpl_vars = array(
            'fields_value' => Config::getSettings('name'),
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
        foreach (Config::getSettings('name') as $key => $value)
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
        $checkoutData = $this->helper->getPaymentData(true);

        // Necessary variables when defining the payment method icon
        $defaultImage = '/modules/mobbex/views/img/logo_transparent.png';
        $image        = !empty(Config::$settings['mobbex_payment_method_image']) ? Config::$settings['mobbex_payment_method_image'] : $defaultImage;
        $method_icon  = (bool) Config::$settings['method_icon'];

        // Get cards and payment methods
        $cards   = isset($checkoutData['wallet']) ? $checkoutData['wallet'] : [];
        $methods = isset($checkoutData['paymentMethods']) ? $checkoutData['paymentMethods'] : [];

        \Mobbex\PS\Checkout\Models\OrderHelper::addJavascriptData([
            'primaryColor' => Config::$settings['color'],
            'paymentUrl'  => \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('payment', 'process'),
            'errorUrl'    => \Mobbex\PS\Checkout\Models\OrderHelper::getUrl('index.php?controller=order&step=3&typeReturn=failure'),
            'embed'       => (bool) Config::$settings['embed'],
            'return'      => \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('notification', 'return', '&id_cart=' . $params['cart']->id),
        ]);

        // Get payment methods from checkout
        if (!Config::$settings['payment_methods'] || isset($checkoutData['sid']) || count($methods) < 1) {
            $options[]    = $this->createPaymentOption(
                Config::$settings['mobbex_title'] ?: $this->l('Paying using cards, cash or others'),
                Config::$settings['mobbex_description'],
                \Media::getMediaPath($image),
                'module:mobbex/views/templates/front/payment.tpl',
                ['checkoutUrl' => \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('payment', 'redirect'), $method_icon],
                Config::$settings['checkout_banner']
            );
        } else {
            foreach ($methods as $method) {
                $checkoutUrl = \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('payment', 'redirect', "&id=$checkoutData[id]&method=$method[group]:$method[subgroup]");
                $options[] = $this->createPaymentOption(
                    (count($methods) == 1 || $method['subgroup'] == 'card_input') && Config::$settings['mobbex_title'] ? Config::$settings['mobbex_title'] : $method['subgroup_title'],
                    (count($methods) == 1 || $method['subgroup'] == 'card_input') ? Config::$settings['mobbex_description'] : null,
                    (count($methods) == 1 || $method['subgroup'] == 'card_input') ? $image : $method['subgroup_logo'],
                    'module:mobbex/views/templates/front/method.tpl',
                    compact('method', 'checkoutUrl', 'method_icon'),
                    (count($methods) == 1 || $method['subgroup'] == 'card_input') ? Config::$settings['checkout_banner'] : ''
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
                    compact('card', 'key', 'method_icon')
                );
            }
        }

        Logger::log('debug', 'Observer > hookPaymentOptions', $options);

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
            Logger::log('error', 'mobbex > hookActionOrderReturn |', $e->getMessage());
            return false;
        }
    }

    /**
     * Logic to execute when the hook 'CustomerFormBuilder' is fired.
     * Add Mobbex own dni field to prestashop admin customer form.
     * Support for 1.7
     * 
     * @param array $params
     */
    public function hookActionCustomerFormBuilderModifier($params)
    {
        // Gets customer and FormBuilder object
        $customer    = \Context::getContext()->customer;
        $formBuilder = isset($params['form_builder']) ? $params['form_builder'] : null;

        if(!isset($formBuilder, $customer)){
            Logger::log('debug', 'Observer > hookActionCustomerFormBuilder', [$formBuilder, $customer]);
            return;
        }

        // Checks if dni is set
        $dni       = isset($customer->id) ? $this->helper->getDni($customer->id) : '';

        // Build customer dni field
        $formBuilder->add(
            'customer_dni',
            'Symfony\Component\Form\Extension\Core\Type\TextType',
            [
                'data'     => $dni,
                'label'    => 'DNI',
                'required' => false,
            ]
        );
        
        // When it is modified in the form, save new dni value in mobbex custom fields table
        if(isset($_POST['customer']['customer_dni'])) {
            // Gets the new dni entered in the form through post
            $dni = $_POST['customer']['customer_dni'];
            \Mobbex\PS\Checkout\Models\CustomFields::saveCustomField($customer->id, 'customer', 'dni', $dni);
        }
    }

    /**
     * Logic to execute when the hook 'AdditionalCustomerFormFields' is fired.
     * Add Mobbex own fields to prestashop checkout.
     */
    public function hookAdditionalCustomerFormFields($params)
    {
        if (Config::$settings['custom_dni'] != '')
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
     * Executes when hook ActionAdminProductsControllerSaveBefore is fired. (Used to update product options). (ps 1.7)
     */
    public function hookActionAdminProductsControllerSaveBefore()
    {
        $this->saveCatalogOptions($_POST['id_product']);
    }

    /**
     * Executes when hook ActionProductUpdate is fired. (Used to update product options). (ps 8)
     */
    public function hookActionProductUpdate()
    {
        $this->saveCatalogOptions(\Tools::getValue('id_product'));
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
                Logger::log('fatal', 'Observer > hookDisplayBackOfficeHeader | Error Obtaining Update/Upgrade Messages', $e->getMessage());
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

            if (Config::$settings['wallet'])
                $this->helper->addAsset('https://res.mobbex.com/js/sdk/mobbex@1.1.0.js');

            if (Config::$settings['embed'])
                $this->helper->addAsset('https://api.mobbex.com/p/embed/1.2.0/lib.js');
        }

        // Product list pages
        if (in_array($currentPage, ['index', 'category', 'manufacturer', 'search', 'newproducts', 'bestsales', 'pricesdrop']) ) {
            $show_tag    = Config::$settings['show_tag_on_products_catalog'] == '1';
            $show_banner = Config::$settings['show_banner_on_products_catalog'] == '1';

            if ($show_tag || $show_banner) {
                $this->helper->addAsset("$mediaPath/views/css/product-tag.css", 'css');
                $this->helper->addAsset("$mediaPath/views/js/product-tag.js");
    
                // Add variables for product tags/banners
                \Media::addJsDef([
                    'mbbx' => [
                        'show_tag'    => $show_tag,
                        'show_banner' => $show_banner
                    ]
                ]);
            }

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
        if ($params['type'] !== 'after_price' || empty($params['product']) || empty($params['product']['show_price']) || !Config::$settings['finance_product'])
            return;

        $id    = [$params['product']['id']];
        $total = $params['product']['price_amount'];

        return $this->displayPlansWidget($total, $id);
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

        if (!\Validate::isLoadedObject($cart) || !Config::$settings['finance_cart'])
            return false;

        $total        = (float) $cart->getOrderTotal(true, \Cart::BOTH);
        $cartProducts = array_column($cart->getProducts(), 'id_product');

        return $this->displayPlansWidget($total, $cartProducts, true);
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
        $checkoutData = $this->helper->getPaymentData(true);

        // Make sure the assets are loaded correctly
        $this->hookDisplayHeader(true);

        // Add payment information to js
        \Media::addJsDef([
            'mbbx' => [
                'primaryColor' => Config::$settings['color'],
                'paymentUrl'   => \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('payment', 'process'),
                'errorUrl'     => \Mobbex\PS\Checkout\Models\OrderHelper::getUrl('index.php?controller=order&step=3&typeReturn=failure'),
                'embed'        => (bool) Config::$settings['embed'],
                'return'       => \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('notification', 'return', '&id_cart=' . \Context::getContext()->cookie->__get('last_cart') . '&status=' . 500)
            ]
        ]);

        $this->smarty->assign([
            'methods'     => isset($checkoutData['paymentMethods']) ? $checkoutData['paymentMethods'] : [],
            'cards'       => isset($checkoutData['wallet']) ? $checkoutData['wallet'] : [],
            'redirectUrl' => \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('payment', 'redirect', isset($checkoutData['id']) ? "&id=$checkoutData[id]" : '')
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

        if (!Config::$settings['finance_product'] || !\Validate::isLoadedObject($product) || !$product->show_price)
            return;

        return $this->displayPlansWidget($product->getPrice(), [$product]);
    }

    /**
     * Show best plan banner in product lists.
     * @param array $params
     * @return string
     */
    public function hookDisplayProductListReviews($params)
    {
        if (
            !isset($params['product']['id_product']) &&
            (Config::$settings['show_tag_on_products_catalog'] == "no"
            || Config::$settings['show_banner_on_products_catalog'] == "no")
        ) {
            return;
        }

        $id       = $params['product']['id_product'];
        $bestPlan = json_decode(Config::getCatalogSetting($id, "bestPlan"), true);

        if (empty($bestPlan)) return;

        $mediaPath = \Media::getMediaPath(_PS_MODULE_DIR_ . 'mobbex');

        $this->smarty->assign([
            'id'          => $id,
            'bestPlan'    => $bestPlan,
            'mediaPath'   => $mediaPath,
            'version'     => \Mobbex\PS\Checkout\Models\Config::MODULE_VERSION,

        ]);

        return $this->display(__FILE__, 'views/templates/hooks/product-tag.tpl');
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
        if (Config::$settings['custom_dni'] != '')
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

        // It only shows the widget if it is an operation made with mobbex
        if ($order->module != 'mobbex')
            return;

        // Get transaction data
        $parent = \Mobbex\PS\Checkout\Models\Transaction::getTransactions($order->id_cart, true);
        $childs = !empty($parent->childs) ? $parent->getChilds() : $parent->loadChildTransactions();

        if (!$parent)
            return;

        // Set the uri to access to the actual page, and a hash to limit the access via capture
        $uri  = urlencode($_SERVER['REQUEST_URI']);
        $hash = md5(Config::$settings['api_key'] . '!' . Config::$settings['access_token']); 

        // Add payment information data and try to create a capture button
        $this->smarty->assign(
            [
                'id'      => $parent->payment_id,
                'cart_id' => $params['id_order'],
                'data'    => [
                    'total'          => $parent->total,
                    'currency'       => $parent->currency,
                    'payment_id'     => $parent->payment_id,
                    'risk_analysis'  => $parent->risk_analysis,
                    'status_message' => $parent->status_message,
                ],
                'capture'    => $parent->status == '3' ? true : false,
                'coupon'     => \Mobbex\PS\Checkout\Models\Transaction::generateCoupon($parent),
                'sources'    => \Mobbex\PS\Checkout\Models\Transaction::getTransactionsSources($parent, $childs),
                'entities'   => \Mobbex\PS\Checkout\Models\Transaction::getTransactionsEntities($parent, $childs),
                'captureUrl' => $this->helper->getModuleUrl('capture', 'captureOrder', "&order_id=$params[id_order]&hash=$hash&url=$uri"),
            ]
        );

        return $this->display(__FILE__, 'views/templates/hooks/order-widget.tpl');
    }

    /** UTIL METHODS REPOSITORY **/

    /**
     * Display finance widget.
     * 
     * @param float|int|string $total Amount to calculate sources.
     * @param array|null       $products_ids
     */
    public function displayPlansWidget($total, $products_ids = [], $cartPage = false)
    {        
        $hash = md5(Config::$settings['api_key'] . '!' . Config::$settings['access_token']);

        // Sets source url to pass it to backend
        $sourcesUrl = \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl(
            "sources",
            "getSources",
            "&hash=$hash&total=$total&mbbxProducts=" . implode(',', $products_ids)
        );

        // Add javascript data to be used in the widget
        $this->helper->addJavascriptData([
            'sourcesUrl'           => $sourcesUrl,
            'theme'                => Config::$settings['theme'],
            'featuredInstallments' => Config::handleFeaturedPlans($products_ids, $cartPage),
            'currencySymbol'       => 
                isset(\Context::getContext()->currency->symbol) ?
                \Context::getContext()->currency->symbol :
                '$',
        ]);;

        $this->smarty->assign([
            'mediaPath' => \Media::getMediaPath(_PS_MODULE_DIR_ . 'mobbex'),
        ]);

        return $this->display(__FILE__, 'views/templates/finance-widget/local.tpl');
    }

    /**
     * Display Mobbex options in catalog config.
     * @param string $id
     * @param string $catalogType
     */
    private function displayCatalogOptions($id, $catalogType = 'product')
    {
        $template = "views/templates/hooks/$catalogType-settings.tpl";

        extract(Config::getCatalogPlans($id, $catalogType, true));
        $filtered_plans = \Mobbex\Repository::getPlansFilterFields($id);
        $plansSettings  = [
            'show_featured',
            'manual_config',
            'featured_plans',
            'advanced_plans',
            'selected_plans'
        ];
        foreach($plansSettings as $setting)
            ${$setting} = Config::getCatalogSetting($id, $setting, $catalogType);

        $settings = [
            'show_featured'  => $show_featured,
            'manual_config'  => $manual_config,
            'filtered_plans' => $filtered_plans,
            'selected_plans' => $selected_plans,
            'advanced_plans' => $advanced_plans,
            'featured_plans' => $featured_plans
        ];

        $options  = [
            'id'             => $id,
            'mbbx'           => $settings,
            'filtered_plans' => $filtered_plans,
            'mediaPath'      => \Media::getMediaPath(_PS_MODULE_DIR_ . 'mobbex'),
            'entity'         => CustomFields::getCustomField($id, $catalogType, 'entity') ?: '',
        ];

        if ($catalogType === 'product') {
            $options['subscription'] = [
                'uid'    => CustomFields::getCustomField($id, 'product', 'subscription_uid') ?: '',
                'enable' => CustomFields::getCustomField($id, 'product', 'subscription_enable') ?: 'no'
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
        $productConfig = _PS_VERSION_ >= '8.0.0' ? $_POST : $_REQUEST;

        $options = [
            'advanced_plans' => "[]",
            'featured_plans' => "[]",
            'selected_plans' => "[]",
            'manual_config'  => 'no',
            'show_featured'  => 'no',
            'entity'         => isset($productConfig['entity']) ? $productConfig['entity'] : null
        ];

        if ($catalogType === 'product') {
            $options['subscription_uid']    = isset($productConfig['sub_uid']) ? $productConfig['sub_uid'] : '';
            $options['subscription_enable'] = isset($productConfig['sub_enable']) ? $productConfig['sub_enable'] : 'no';
        }

        // plans configurator settings
        $options['manual_config']  = isset($productConfig['mobbex_manual_config']) ? $productConfig['mobbex_manual_config'] : 'no';
        $options['featured_plans'] = isset($productConfig['mobbex_featured_plans']) ? $productConfig['mobbex_featured_plans'] : "[]";
        $options['selected_plans'] = isset($productConfig['mobbex_selected_plans']) ? $productConfig['mobbex_selected_plans'] : "[]";
        $options['advanced_plans'] = isset($productConfig['mobbex_advanced_plans']) ? $productConfig['mobbex_advanced_plans'] : "[]";
        $options['show_featured']  = isset($productConfig['mobbex_show_featured_plans']) ? $productConfig['mobbex_show_featured_plans'] : 'no';

        foreach ($options as $key => $value)
            CustomFields::saveCustomField($id, $catalogType, $key, $value);

        // save best plan to show in produts catalog page
        if ($catalogType == "product")
            $this->saveBestPlan($id);
    }

    /**
     * @param array $params
     */
    private function updateCustomerDniStatus(array $params)
    {
        if (!Config::$settings['mobbex_dni'] || empty($params['object']->id) || empty($_POST['customer_dni']) || Config::$settings['custom_dni'] != '')
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
     * @param string $banner
     * 
     * @return Object
     */
    private function createPaymentOption($title, $description, $logo, $template, $templateVars = null, $banner = '')
    {
        if ($templateVars)
            $this->smarty->assign($templateVars);

        $extraInfo = '';
        
        //Add banner
        if($banner)
            $extraInfo .= "<img src='$banner' class='mbbx-banner'>";
        //Add description
        if($description)
            $extraInfo .= "<p>$description</p>";

        $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($title)
            ->setForm($this->smarty->fetch($template))
            ->setAdditionalInformation($extraInfo ? "<section class='mbbx-extra'>$extraInfo</section>" : '');

        if(Config::$settings['method_icon'])
            $option->setLogo($logo);

        return $option;
    }

    /**
     * saveBestPlan saves the required data to show the best plan banner in products catalog page
     * 
     * @param object $product
     */
    private function saveBestPlan($id)
    {
        $product = new \Product($id[0], false, (int) \Configuration::get('PS_LANG_DEFAULT'));

        $featuredPlans = Config::getAllPlansConfiguratorSettings($id, $product, "manual_config")
            ? Config::getAllPlansConfiguratorSettings($id, $product, "featured_plans")
            : null;

        if (empty($featuredPlans))
            return null;

        $price     = $product->getPrice();
        $bestPlan = $this->getBestPlan($featuredPlans, $id, $price);

        CustomFields::saveCustomField($id, 'product', 'bestPlan', $bestPlan);
    }

    /**
     * getBestPlan get the best plan configured as featured plan for a product
     * 
     * @param array      $featuredPlans
     * @param int|string $id
     * @param int|string $price
     * 
     * @return null|string best plan in featured plans
     */
    private function getBestPlan($featuredPlans, $id, $price) 
    {
        $sources = [];

        // Get product plans
        extract(Config::getProductsPlans([$id]));

        $installments = \Mobbex\Repository::getInstallments(
            [$id], 
            $common_plans,
            $advanced_plans
        );

        // Get sources from cache or Mobbex API
        try {
            $sources = \Mobbex\Repository::getSources(
                $price,
                $installments
            );
        }  catch (\Exception $e) {
            \Mobbex\PS\Checkout\Models\Logger::log(
                'error', 
                'Mobbex > getBestPlan > getSources', 
                $e->getMessage()
            );
            return null;
        }
        
        if (empty($sources))
            return null;

        return $this->helper->filterFeaturedPlans($sources, $featuredPlans);
    }
}
