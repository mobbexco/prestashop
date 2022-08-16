<?php

/**
 * mobbex.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 3.1.1
 * @see     PaymentModuleCore
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/Exception.php';
require_once dirname(__FILE__) . '/classes/Model.php';
require_once dirname(__FILE__) . '/classes/Task.php';
require_once dirname(__FILE__) . '/classes/Api.php';
require_once dirname(__FILE__) . '/classes/Updater.php';
require_once dirname(__FILE__) . '/classes/OrderUpdate.php';
require_once dirname(__FILE__) . '/classes/MobbexHelper.php';
require_once dirname(__FILE__) . '/classes/MobbexTransaction.php';
require_once dirname(__FILE__) . '/classes/MobbexCustomFields.php';

/**
 * Main class of the module
 */
class Mobbex extends PaymentModule
{
    /** @var \Mobbex\Updater */
    public $updater;
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 'mobbex';

        $this->tab = 'payments_gateways';

        $this->version = MobbexHelper::MOBBEX_VERSION;

        $this->author = 'Mobbex Co';
        $this->controllers = ['notification', 'payment', 'task', 'sources'];
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Mobbex');
        $this->description = $this->l('Payment plugin using Mobbex ');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        // On 1.7.5 ignores the creation and finishes on an Fatal Error
        // Create the States if not exists because are really important
        if ($this::isEnabled($this->name))
            $this->_createStates();

        // Only if you want to publish your module on the Addons Marketplace
        $this->module_key = 'mobbex_checkout';
        $this->updater = new \Mobbex\Updater();
        $this->settings = $this->getSettings();

        // Execute pending tasks if cron is disabled
        if (!defined('mobbexTasksExecuted') && !\Configuration::get('MOBBEX_CRON_MODE') && !MobbexHelper::needUpgrade())
            define('mobbexTasksExecuted', true) && MobbexTask::executePendingTasks();
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

        // Don't forget to check for PHP extensions like curl here
        Configuration::updateValue(MobbexHelper::K_API_KEY, '');
        Configuration::updateValue(MobbexHelper::K_ACCESS_TOKEN, '');
        Configuration::updateValue(MobbexHelper::K_TEST_MODE, false);
        Configuration::updateValue(MobbexHelper::K_EMBED, true);
        Configuration::updateValue(MobbexHelper::K_WALLET, false);
        Configuration::updateValue(MobbexHelper::K_UNIFIED_METHOD, false);
        Configuration::updateValue(MobbexHelper::K_MULTIVENDOR, MobbexHelper::K_DEF_MULTIVENDOR);
        Configuration::updateValue(MobbexHelper::K_MULTICARD, MobbexHelper::K_DEF_MULTICARD);
        // Theme
        Configuration::updateValue(MobbexHelper::K_THEME, MobbexHelper::K_DEF_THEME);
        Configuration::updateValue(MobbexHelper::K_THEME_BACKGROUND, MobbexHelper::K_DEF_BACKGROUND);
        Configuration::updateValue(MobbexHelper::K_THEME_PRIMARY, MobbexHelper::K_DEF_PRIMARY);
        // Plans Widget
        Configuration::updateValue(MobbexHelper::K_PLANS, false);
        Configuration::updateValue(MobbexHelper::K_PLANS_TEXT, MobbexHelper::K_DEF_PLANS_TEXT);
        Configuration::updateValue(MobbexHelper::K_PLANS_STYLES, MobbexHelper::K_DEF_PLANS_STYLES);
        Configuration::updateValue(MobbexHelper::K_PLANS_IMAGE_URL, MobbexHelper::K_DEF_PLANS_IMAGE_URL);
        // DNI Fields
        Configuration::updateValue(MobbexHelper::K_OWN_DNI, false);
        Configuration::updateValue(MobbexHelper::K_CUSTOM_DNI, '');
        //Order Statuses
        Configuration::updateValue(MobbexHelper::K_ORDER_STATUS_APPROVED, \Configuration::get('PS_OS_' . 'PAYMENT'));
        Configuration::updateValue(MobbexHelper::K_ORDER_STATUS_FAILED, \Configuration::get('PS_OS_' . 'ERROR'));
        Configuration::updateValue(MobbexHelper::K_ORDER_STATUS_REFUNDED, \Configuration::get('PS_OS_' . 'REFUND'));
        Configuration::updateValue(MobbexHelper::K_ORDER_STATUS_REJECTED, \Configuration::get('PS_OS_' . 'ERROR'));

        $this->_createTable();
        return parent::install() && $this->unregisterHooks() && $this->registerHooks() && $this->addExtensionHooks();
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
        if (isset($_COOKIE['mbbx_remove_config']) && $_COOKIE['mbbx_remove_config'] === 'true') {
            // Only delete main module settings values
            $settings = $this->getSettings(false);

            foreach ($settings as $name => $value)
                Configuration::deleteByName($name);
        }

        return parent::uninstall();
    }

    /**
     * Register module hooks dependig on prestashop version.
     * 
     * @return bool Result of the registration
     */
    public function registerHooks()
    {
        $hooks = [
            'displayAdminProductsExtra',
            'actionAdminProductsControllerSaveBefore',
            'displayBackOfficeCategory',
            'categoryAddition',
            'categoryUpdate',
            'displayPDFInvoice',
            'displayBackOfficeHeader',
            'paymentReturn',
            'actionOrderReturn',
            'displayAdminOrder',
            'actionMobbexExpireOrder',
        ];

        $ps16Hooks = [
            'payment',
            'header',
            'displayMobileHeader',
            'displayProductButtons',
            'displayCustomerAccountForm',
            'actionCustomerAccountAdd',
            'displayShoppingCartFooter',
        ];

        $ps17Hooks = [
            'paymentOptions',
            'displayHeader',
            'additionalCustomerFormFields',
            'actionObjectCustomerUpdateAfter',
            'actionObjectCustomerAddAfter',
            'displayProductPriceBlock',
            'displayExpressCheckout',
        ];

        // Merge current version hooks with common hooks
        $hooks = array_merge($hooks, _PS_VERSION_ > '1.7' ? $ps17Hooks : $ps16Hooks);

        foreach ($hooks as $hookName) {
            if (!$this->registerHook($hookName))
                return false;
        }

        return true;
    }

    /**
     * Unregister all current module hooks.
     * 
     * @return bool Result.
     */
    public function unregisterHooks()
    {
        // Get hooks used by module
        $hooks = Db::getInstance()->executeS(
            'SELECT DISTINCT(`id_hook`) FROM `' . _DB_PREFIX_ . 'hook_module` WHERE `id_module` = ' . $this->id
        ) ?: [];

        foreach ($hooks as $hook) {
            if (!$this->unregisterHook($hook['id_hook']) || !$this->unregisterExceptions($hook['id_hook']))
                return false;
        }

        return true;
    }

    /**
     * Create own hooks to extend features in external modules.
     * 
     * @return bool Result of addition.
     */
    public function addExtensionHooks()
    {
        $hooks = [
            'actionMobbexCheckoutRequest' => [
                'title'       => 'Create checkout request',
                'description' => 'Modify checkout request posted data'
            ],
            'actionMobbexProcessPayment' => [
                'title'       => 'Process payment data',
                'description' => 'Add custom payment data response before display payment options'
            ],
            'displayMobbexConfiguration' => [
                'title'       => 'Modify mobbex configuration form',
                'description' => 'Modify main mobbex configuration form data'
            ],
            'displayMobbexProductSettings' => [
                'title'       => 'Product admin additionals fields',
                'description' => 'Display additional fields in mobbex configuration tab of product'
            ],
            'displayMobbexCategorySettings' => [
                'title'       => 'Category admin additionals fields',
                'description' => 'Display additional fields in mobbex configuration tab of category'
            ],
            'displayMobbexOrderWidget' => [
                'title'       => 'Mobbex order widget aditional info',
                'description' => 'Display additional info in Mobbex order widget'
            ]
        ];

        foreach ($hooks as $name => $data) {
            if (!Hook::getIdByName($name)) {
                $hook              = new Hook();
                $hook->name        = $name;
                $hook->title       = $data['title'];
                $hook->description = $data['description'];

                if (!$hook->add())
                    return false;
            }
        }

        return true;
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
            Tools::redirectAdmin(MobbexHelper::getUpgradeURL());
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
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit_mobbex';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $form = $this->getConfigForm(true);

        try {
            if (MobbexHelper::needUpgrade())
                $form['form']['warning'] = 'Actualice la base de datos desde <a href="' . MobbexHelper::getUpgradeURL() . '">aquí</a> para que el módulo funcione correctamente.';

            if ($this->updater->hasUpdates(MobbexHelper::MOBBEX_VERSION))
                $form['form']['description'] = "¡Nueva actualización disponible! Haga <a href='$_SERVER[REQUEST_URI]&run_update=1'>clic aquí</a> para actualizar a la versión " . $this->updater->latestRelease['tag_name'];
        } catch (\Exception $e) {
            MobbexHelper::log('Mobbex: Error Obtaining Update/Upgrade Messages' . $e->getMessage(), null, true);
        }

        $helper->tpl_vars = array(
            'fields_value' => $this->settings,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm([$form]);
    }

    public function _createTable()
    {
        $db = DB::getInstance();

        $db->execute("SHOW TABLES LIKE '" . _DB_PREFIX_ . "mobbex_transaction';");

        // If mobbex transaction table exists
        if ($db->numRows()) {
            $this->_alterTable();
        } else {
            $db->execute(
                "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "mobbex_transaction` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `cart_id` INT(11) NOT NULL,
                    `parent` TEXT NOT NULL,
                    `payment_id` TEXT NOT NULL,
                    `description` TEXT NOT NULL,
                    `status_code` TEXT NOT NULL,
                    `status` TEXT NOT NULL,
                    `status_message` TEXT NOT NULL,
                    `source_name` TEXT NOT NULL,
                    `source_type` TEXT NOT NULL,
                    `source_reference` TEXT NOT NULL,
                    `source_number` TEXT NOT NULL,
                    `source_expiration` TEXT NOT NULL,
                    `source_installment` TEXT NOT NULL,
                    `installment_name` TEXT NOT NULL,
                    `source_url` TEXT NOT NULL,
                    `cardholder` TEXT NOT NULL,
                    `entity_name` TEXT NOT NULL,
                    `entity_uid` TEXT NOT NULL,
                    `customer` TEXT NOT NULL,
                    `checkout_uid` TEXT NOT NULL,
                    `total` DECIMAL(18,2) NOT NULL,
                    `currency` TEXT NOT NULL,
                    `risk_analysis` TEXT NOT NULL,
                    `data` TEXT NOT NULL,
                    `created` TEXT NOT NULL,
                    `updated` TEXT NOT NULL
                ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;"
            );
        }

        $db->execute(
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "mobbex_custom_fields` (
                `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `row_id` INT(11) NOT NULL,
                `object` TEXT NOT NULL,
                `field_name` TEXT NOT NULL,
                `data` TEXT NOT NULL
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;"
        );

        $db->execute(
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "mobbex_task` (
                `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` TEXT NOT NULL,
                `args` TEXT NOT NULL,
                `interval` INT(11) NOT NULL,
                `period` TEXT NOT NULL,
                `limit` INT(11) NOT NULL,
                `executions` INT(11) NOT NULL,
                `start_date` DATETIME NOT NULL,
                `last_execution` DATETIME NOT NULL,
                `next_execution` DATETIME NOT NULL
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;"
        );

        return true;
    }

    public function _alterTable()
    {
        $db = DB::getInstance();

        // Check if table has already been modified
        if ($db->executeS("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "mobbex_transaction` WHERE FIELD = 'id' AND EXTRA LIKE '%auto_increment%';"))
            return true;

        // If it was modified but id has not auto_increment property, add to column
        if ($db->executeS("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "mobbex_transaction` WHERE FIELD = 'id';"))
            return $db->execute("ALTER TABLE `" . _DB_PREFIX_ . "mobbex_transaction` MODIFY `id` INT NOT NULL AUTO_INCREMENT;");

        $db->execute(
            "ALTER TABLE `" . _DB_PREFIX_ . "mobbex_transaction`
                DROP PRIMARY KEY,
                ADD `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                ADD `parent` BOOLEAN NOT NULL,
                ADD `payment_id` TEXT NOT NULL,
                ADD `description` TEXT NOT NULL,
                ADD `status_code` TEXT NOT NULL,
                ADD `status` TEXT NOT NULL,
                ADD `status_message` TEXT NOT NULL,
                ADD `source_name` TEXT NOT NULL,
                ADD `source_type` TEXT NOT NULL,
                ADD `source_reference` TEXT NOT NULL,
                ADD `source_number` TEXT NOT NULL,
                ADD `source_expiration` TEXT NOT NULL,
                ADD `source_installment` TEXT NOT NULL,
                ADD `installment_name` TEXT NOT NULL,
                ADD `source_url` TEXT NOT NULL,
                ADD `cardholder` TEXT NOT NULL,
                ADD `entity_name` TEXT NOT NULL,
                ADD `entity_uid` TEXT NOT NULL,
                ADD `customer` TEXT NOT NULL,
                ADD `checkout_uid` TEXT NOT NULL,
                ADD `total` DECIMAL(18,2) NOT NULL,
                ADD `currency` TEXT NOT NULL,
                ADD `risk_analysis` TEXT NOT NULL,
                ADD `created` TEXT NOT NULL,
                ADD `updated` TEXT NOT NULL,
            ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;"
        );

        return true;
    }

    private function _createStates()
    {
        // Approved Status
        if (
            !Configuration::hasKey(MobbexHelper::K_OS_APPROVED)
            || empty(Configuration::get(MobbexHelper::K_OS_APPROVED))
            || !Validate::isLoadedObject(new OrderState(Configuration::get(MobbexHelper::K_OS_APPROVED)))
        ) {
            $order_state = new OrderState();
            $order_state->name = array();

            foreach (Language::getLanguages() as $language) {
                // The locale parameter does not work as it should, so it is impossible to get the translation for each language
                $order_state->name[$language['id_lang']] = $this->l('Transaction in Process');
            }

            $order_state->send_email = true;
            $order_state->color = '#5bff67';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;

            $order_state->module_name = $this->name;

            // Add to database
            $order_state->add();
            Configuration::updateValue(MobbexHelper::K_OS_APPROVED, (int) $order_state->id);
        }

        // Pending Status
        if (
            !Configuration::hasKey(MobbexHelper::K_OS_PENDING)
            || empty(Configuration::get(MobbexHelper::K_OS_PENDING))
            || !Validate::isLoadedObject(new OrderState(Configuration::get(MobbexHelper::K_OS_PENDING)))
        ) {
            $order_state = new OrderState();
            $order_state->name = array();

            foreach (Language::getLanguages() as $language) {
                // The locale parameter does not work as it should, so it is impossible to get the translation for each language
                $order_state->name[$language['id_lang']] = $this->l('Pending');
            }

            $order_state->send_email = false;
            $order_state->color = '#FEFF64';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;

            $order_state->module_name = $this->name;

            // Add to database
            $order_state->add();
            Configuration::updateValue(MobbexHelper::K_OS_PENDING, (int) $order_state->id);
        }

        // Waiting Status
        if (
            !Configuration::hasKey(MobbexHelper::K_OS_WAITING)
            || empty(Configuration::get(MobbexHelper::K_OS_WAITING))
            || !Validate::isLoadedObject(new OrderState(Configuration::get(MobbexHelper::K_OS_WAITING)))
        ) {
            $order_state = new OrderState();
            $order_state->name = array();

            foreach (Language::getLanguages() as $language)
                $order_state->name[$language['id_lang']] = $this->l('Waiting');

            $order_state->send_email = false;
            $order_state->color = '#FEFF64';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;

            $order_state->module_name = $this->name;

            // Add to database
            $order_state->add();
            Configuration::updateValue(MobbexHelper::K_OS_WAITING, (int) $order_state->id);
        }

        // Rejected Status
        if (
            !Configuration::hasKey(MobbexHelper::K_OS_REJECTED)
            || empty(Configuration::get(MobbexHelper::K_OS_REJECTED))
            || !Validate::isLoadedObject(new OrderState(Configuration::get(MobbexHelper::K_OS_REJECTED)))
        ) {
            $order_state = new OrderState();
            $order_state->name = array();

            foreach (Language::getLanguages() as $language)
                $order_state->name[$language['id_lang']] = $this->l('Rejected Payment');

            $order_state->send_email = false;
            $order_state->color = '#8F0621';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;

            $order_state->module_name = $this->name;

            // Add to database
            $order_state->add();
            Configuration::updateValue(MobbexHelper::K_OS_REJECTED, (int) $order_state->id);
        }
    }

    /**
     * Logic to apply when the configuration form is posted
     *
     * @return void
     */
    public function postProcess()
    {
        foreach ($this->settings as $name => $value)
            Configuration::updateValue($name, Tools::getValue($name));
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

    /**
     * Logic to execute when the hook 'displayPaymentReturn' is fired
     *
     * @return string
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false) {
            return;
        }

        /** @var Order $order */
        if (isset($params['order'])) {
            $order = $params['order'];
        } else {
            $order = $params['objOrder'];
        }

        if ($order) {

            // Get Transaction Data
            $transactions = MobbexTransaction::getTransactions($order->id_cart);
            $trx = MobbexTransaction::getParentTransaction($order->id_cart);
            $sources = MobbexHelper::getWebhookSources($transactions);

            // Assign the Data into Smarty
            $this->smarty->assign('status', $order->getCurrentStateFull($this->context->language->id)['name']);
            $this->smarty->assign('total', $trx->total);
            $this->smarty->assign('payment', $order->payment);
            $this->smarty->assign('status_message', $trx->status_message);
            $this->smarty->assign('sources', $sources);
        }

        return $this->display(__FILE__, 'views/templates/hooks/orderconfirmation.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart']) || !MobbexHelper::isPaymentStep())
            return;

        $options = [];
        $checkoutData = MobbexHelper::getPaymentData();

        // Get cards and payment methods
        $cards   = isset($checkoutData['wallet']) ? $checkoutData['wallet'] : [];
        $methods = isset($checkoutData['paymentMethods']) ? $checkoutData['paymentMethods'] : [];

        \MobbexHelper::addJavascriptData([
            'paymentUrl'  => \MobbexHelper::getModuleUrl('payment', 'process'),
            'errorUrl'    => \MobbexHelper::getUrl('index.php?controller=order&step=3&typeReturn=failure'),
            'embed'       => (bool) Configuration::get(MobbexHelper::K_EMBED),
            'data'        => $checkoutData,
            'return'      => MobbexHelper::getModuleUrl('notification', 'return', '&id_cart=' . $params['cart']->id . '&status=' . 500),
        ]);

        // Get payment methods from checkout
        if (Configuration::get(MobbexHelper::K_UNIFIED_METHOD) || isset($checkoutData['sid'])) {
            $options[] = $this->createPaymentOption(
                Configuration::get('MOBBEX_TITLE') ?: $this->l('Paying using cards, cash or others'),
                Configuration::get('MOBBEX_DESCRIPTION'),
                Media::getMediaPath(_PS_MODULE_DIR_ . 'mobbex/views/img/logo_transparent.png'),
                'module:mobbex/views/templates/front/payment.tpl',
                ['checkoutUrl' => MobbexHelper::getModuleUrl('payment', 'redirect', "&id=$checkoutData[id]")]
            );
        } else {
            foreach ($methods as $method) {
                $checkoutUrl = MobbexHelper::getModuleUrl('payment', 'redirect', "&id=$checkoutData[id]&method=$method[group]:$method[subgroup]");

                $options[] = $this->createPaymentOption(
                    (count($methods) == 1 || $method['subgroup'] == 'card_input') && Configuration::get('MOBBEX_TITLE') ? Configuration::get('MOBBEX_TITLE') : $method['subgroup_title'],
                    (count($methods) == 1 || $method['subgroup'] == 'card_input') ? Configuration::get('MOBBEX_DESCRIPTION') : null,
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

        return $options;
    }

    public function createPaymentOption($title, $description, $logo, $template, $templateVars = null)
    {
        if ($templateVars)
            $this->context->smarty->assign($templateVars);

        $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($title)
            ->setForm($this->context->smarty->fetch($template))
            ->setLogo($logo)
            ->setAdditionalInformation($description ? "<section><p>$description</p></section>" : '');

        return $option;
    }

    public function hookDisplayProductPriceBlock($params)
    {
        if ($params['type'] !== 'after_price' || empty($params['product']) || empty($params['product']['show_price']) || !Configuration::get(MobbexHelper::K_PLANS))
            return;

        return $this->displayPlansWidget($params['product']['price_amount'], [$params['product']['id']]);
    }

    /**
     * Display finance widget.
     * 
     * @param float|int|string $total Amount to calculate sources.
     * @param array|null $products
     */
    public function displayPlansWidget($total, $products = [])
    {
        $this->context->smarty->assign([
            'product_price'  => Product::convertAndFormatPrice($total),
            'sources'        => MobbexHelper::getSources($total, MobbexHelper::getInstallments($products)),
            'style_settings' => [
                'default_styles' => Tools::getValue('controller') == 'cart' || Tools::getValue('controller') == 'order',
                'styles'         => Configuration::get(MobbexHelper::K_PLANS_STYLES) ?: MobbexHelper::K_DEF_PLANS_STYLES,
                'text'           => Configuration::get(MobbexHelper::K_PLANS_TEXT) ?: MobbexHelper::K_DEF_PLANS_TEXT,
                'button_image'   => Configuration::get(MobbexHelper::K_PLANS_IMAGE_URL),
                'plans_theme'    => Configuration::get(MobbexHelper::K_THEME) ?: MobbexHelper::K_DEF_THEME,
            ],
        ]);

        return $this->display(__FILE__, 'views/templates/finance-widget/' . ('local.tpl'));
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
        $cart = Context::getContext()->cart;

        if (!Validate::isLoadedObject($cart) || !Configuration::get('MOBBEX_PLANS_ON_CART'))
            return false;

        return $this->displayPlansWidget((float) $cart->getOrderTotal(true, Cart::BOTH), array_column($cart->getProducts(), 'id_product'));
    }

    public function hookAdditionalCustomerFormFields($params)
    {
        if (!Configuration::get(MobbexHelper::K_OWN_DNI, false) || Configuration::get(MobbexHelper::K_CUSTOM_DNI, '') != '') {
            return;
        }
        $customer = Context::getContext()->customer;

        $dni_field = array();
        $dni_field['customer_dni'] = (new FormField)
            ->setName('customer_dni')
            ->setValue(isset($customer->id) ? MobbexHelper::getDni($customer->id) : '')
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

    private function updateCustomerDniStatus(array $params)
    {
        if (!Configuration::get(MobbexHelper::K_OWN_DNI, false) || empty($params['object']->id) || empty($_POST['customer_dni']) || Configuration::get(MobbexHelper::K_CUSTOM_DNI, '') != '') {
            return;
        }

        $customer_id = $params['object']->id;
        $customer_dni = $_POST['customer_dni'];

        return DB::getInstance()->saveCustomField($customer_id, 'customer_dni', 'customer_dni', $customer_dni);
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
        $checkoutData = MobbexHelper::getPaymentData();

        // Make sure the assets are loaded correctly
        $this->hookDisplayHeader(true);

        // Add payment information to js
        Media::addJsDef([
            'mbbx' => [
                'paymentUrl' => \MobbexHelper::getModuleUrl('payment', 'process'),
                'errorUrl'   => \MobbexHelper::getUrl('index.php?controller=order&step=3&typeReturn=failure'),
                'embed'      => (bool) Configuration::get(MobbexHelper::K_EMBED),
                'data'       => $checkoutData,
                'return'     => MobbexHelper::getModuleUrl('notification', 'return', '&id_cart=' . Context::getContext()->cookie->__get('last_cart') . '&status=' . 500)
            ]
        ]);

        $this->context->smarty->assign([
            'methods'     => isset($checkoutData['paymentMethods']) && !Configuration::get(MobbexHelper::K_UNIFIED_METHOD) ? $checkoutData['paymentMethods'] : [],
            'cards'       => isset($checkoutData['wallet']) ? $checkoutData['wallet'] : [],
            'redirectUrl' => isset($checkoutData['id']) ? MobbexHelper::getModuleUrl('payment', 'redirect', "&id=$checkoutData[id]") : '',
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
        $product = new Product(Tools::getValue('id_product'));

        if (!Configuration::get(MobbexHelper::K_PLANS) || !Validate::isLoadedObject($product) || !$product->show_price)
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
        if (!Configuration::get(MobbexHelper::K_OWN_DNI, false) || Configuration::get(MobbexHelper::K_CUSTOM_DNI, '') != '') {
            return;
        }
        $customer = Context::getContext()->customer;
        $template = 'views/templates/hooks/dnifield.tpl';

        $this->context->smarty->assign(
            array(
                'last_dni' => isset($customer->id) ? MobbexHelper::getDni($customer->id) : "",
            )
        );

        return $this->display(__FILE__, $template);
    }

    public function hookActionOrderReturn($params)
    {
        $order = new Order($params['orderReturn']->orderId);

        if ($order->module != 'mobbex')
            return true;

        // Process refund
        $trans  = MobbexTransaction::getParentTransaction($order->id_cart);
        $result = MobbexHelper::processRefund($order->getTotalPaid(), $trans['payment_id']);

        // Update order status
        if ($result) {
            $order->setCurrentState((int) Configuration::get('PS_OS_REFUND'));
            $order->save();
        }

        return $result;
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
        $customer = Context::getContext()->customer;

        $params['object'] = isset($customer->id) ? $customer : "";
        $this->updateCustomerDniStatus($params);
    }

    /**
     * Show product admin settings.
     * 
     * @param array $params
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        $id   = !empty($params['id_product']) ? $params['id_product'] : Tools::getValue('id_product');
        $hash = md5(\Configuration::get(MobbexHelper::K_API_KEY) . '!' . \Configuration::get(MobbexHelper::K_ACCESS_TOKEN));

        $this->context->smarty->assign([
            'id'             => $id,
            'update_sources' => MobbexHelper::getModuleUrl('sources', 'update', "&hash=$hash"),
            'plans'          => MobbexHelper::getPlansFilterFields($id),
            'entity'         => MobbexCustomFields::getCustomField($id, 'product', 'entity') ?: '',
            'subscription' => [
                'uid'    => MobbexCustomFields::getCustomField($id, 'product', 'subscription_uid') ?: '',
                'enable' => MobbexCustomFields::getCustomField($id, 'product', 'subscription_enable') ?: 'no'
            ]
        ]);

        return $this->display(__FILE__, 'views/templates/hooks/product-settings.tpl');
    }

    /**
     * Show category admin settings.
     * 
     * @param array $params
     */
    public function hookDisplayBackOfficeCategory($params)
    {
        $id = !empty($params['request']) ? $params['request']->get('categoryId') : Tools::getValue('id_category');
        $hash = md5(\Configuration::get(MobbexHelper::K_API_KEY) . '!' . \Configuration::get(MobbexHelper::K_ACCESS_TOKEN));

        $this->context->smarty->assign([
            'id'             => $id,
            'update_sources' => MobbexHelper::getModuleUrl('sources', 'update', "&hash=$hash"),
            'plans'          => MobbexHelper::getPlansFilterFields($id, 'category'),
            'entity'         => MobbexCustomFields::getCustomField($id, 'category', 'entity') ?: ''
        ]);

        return $this->display(__FILE__, 'views/templates/hooks/category-settings.tpl');
    }

    /**
     * Show the mobbex order widget in the order panel backoffice.
     * 
     * @param array $params
     */
    public function hookDisplayAdminOrder($params)
    {
        $order = new Order($params['id_order']);
        $trx = MobbexTransaction::getParentTransaction($order->id_cart);
        $transactions = MobbexTransaction::getTransactions($order->id_cart);

        if (!$trx) {
            return;
        }

        $this->context->smarty->assign(
            [
                'id' => $trx->payment_id,
                'data' => [
                    'payment_id'    => $trx->payment_id,
                    'risk_analysis' => $trx->risk_analysis,
                    'currency'      => $trx->currency,
                    'total'         => $trx->total,
                ],
                'sources'  => MobbexHelper::getWebhookSources($transactions),
                'entities' => MobbexHelper::getWebhookEntities($transactions)
            ]
        );

        return $this->display(__FILE__, 'views/templates/hooks/order-widget.tpl');
    }

    public function hookActionAdminProductsControllerSaveBefore()
    {
        $commonPlans = $advancedPlans = [];

        $entity          = isset($_REQUEST['entity'])     ? $_REQUEST['entity']     : null;
        $isSubscription  = isset($_REQUEST['sub_enable']) ? $_REQUEST['sub_enable'] : 'no';
        $subscriptionUid = isset($_REQUEST['sub_uid'])    ? $_REQUEST['sub_uid']    : '';

        // Get plans selected
        foreach ($_REQUEST as $key => $value) {
            if (strpos($key, 'common_plan_') !== false && $value === 'no') {
                // Add UID to common plans
                $commonPlans[] = explode('common_plan_', $key)[1];
            } else if (strpos($key, 'advanced_plan_') !== false && $value === 'yes') {
                // Add UID to advanced plans
                $advancedPlans[] = explode('advanced_plan_', $key)[1];
            }
        }

        // Save data directly
        MobbexCustomFields::saveCustomField($_POST['id_product'], 'product', 'entity', $entity);
        MobbexCustomFields::saveCustomField($_POST['id_product'], 'product', 'common_plans', json_encode($commonPlans));
        MobbexCustomFields::saveCustomField($_POST['id_product'], 'product', 'advanced_plans', json_encode($advancedPlans));
        MobbexCustomFields::saveCustomField($_POST['id_product'], 'product', 'subscription_enable', $isSubscription);
        MobbexCustomFields::saveCustomField($_POST['id_product'], 'product', 'subscription_uid', $subscriptionUid);
    }

    /**
     * Save the selected payment plans in the category page
     */
    public function hookCategoryAddition($params)
    {
        return $this->hookCategoryUpdate($params);
    }

    /**
     * Save the selected payment plans in the category edit page
     */
    public function hookCategoryUpdate($params)
    {
        $commonPlans = $advancedPlans = [];
        $entity = isset($_POST['entity']) ? $_POST['entity'] : null;

        // Get plans selected
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'common_plan_') !== false && $value === 'no') {
                // Add UID to common plans
                $commonPlans[] = explode('common_plan_', $key)[1];
            } else if (strpos($key, 'advanced_plan_') !== false && $value === 'yes') {
                // Add UID to advanced plans
                $advancedPlans[] = explode('advanced_plan_', $key)[1];
            }
        }

        // If is bulk import
        if (strnatcasecmp(Tools::getValue('controller'), 'adminImport') === 0) {
            // Only save when they are not empty
            if (!empty($commonPlans))
                MobbexCustomFields::saveCustomField($params['category']->id, 'category', 'common_plans', json_encode($commonPlans));
            if (!empty($advancedPlans))
                MobbexCustomFields::saveCustomField($params['category']->id, 'category', 'advanced_plans', json_encode($advancedPlans));
            if ($entity)
                MobbexCustomFields::saveCustomField($params['category']->id, 'category', 'entity', $entity);
        } else {
            // Save data directly
            MobbexCustomFields::saveCustomField($params['category']->id, 'category', 'entity', $entity);
            MobbexCustomFields::saveCustomField($params['category']->id, 'category', 'common_plans', json_encode($commonPlans));
            MobbexCustomFields::saveCustomField($params['category']->id, 'category', 'advanced_plans', json_encode($advancedPlans));
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
        $tab = MobbexHelper::getInvoiceData($params['object']->id_order);

        return $tab;
    }

    /**
     * Load front end scripts.
     * 
     * @param bool $force Ignore page name check to load scripts.
     */
    public function hookDisplayHeader($force = false)
    {
        $currentPage = Tools::getValue('controller');
        $mediaPath   = Media::getMediaPath(_PS_MODULE_DIR_ . $this->name);

        // Checkout page
        if ($currentPage == 'order' || $force) {
            MobbexHelper::addAsset("$mediaPath/views/css/front.css", 'css');
            MobbexHelper::addAsset("$mediaPath/views/js/front.js");

            if (Configuration::get(MobbexHelper::K_WALLET))
                MobbexHelper::addAsset('https://res.mobbex.com/js/sdk/mobbex@1.1.0.js');

            if (Configuration::get(MobbexHelper::K_EMBED))
                MobbexHelper::addAsset('https://res.mobbex.com/js/embed/mobbex.embed@1.0.20.js');
        }
    }

    /**
     * Load back office scripts.
     * 
     * @return void
     */
    public function hookDisplayBackOfficeHeader()
    {
        $currentPage = Tools::getValue('controller');
        $mediaPath   = Media::getMediaPath(_PS_MODULE_DIR_ . $this->name);

        // Module Manager page
        if ($currentPage == 'AdminModulesManage')
            MobbexHelper::addAsset("$mediaPath/views/js/uninstall-options.js");

        // Configuration page
        if ($currentPage == 'AdminModules' && Tools::getValue('configure') == 'mobbex') {
            MobbexHelper::addAsset("$mediaPath/views/js/mobbex-config.js");

            try {
                // If plugin has updates, add update data to javascript
                if ($this->updater->hasUpdates(MobbexHelper::MOBBEX_VERSION))
                    MobbexHelper::addJavascriptData(['updateVersion' => $this->updater->latestRelease['tag_name']]);
            } catch (\Exception $e) {
                MobbexHelper::log('Mobbex: Error Obtaining Update/Upgrade Messages' . $e->getMessage(), null, true);
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

    /**
     * Support displayHeader hook aliases.
     */
    public function hookDisplayMobileHeader()
    {
        return $this->hookDisplayHeader();
    }

    public function hookActionEmailSendBefore($params)
    {
        if ($params['template'] == 'order_conf' && !empty($params['templateVars']['id_order'])) {
            $order = new Order($params['templateVars']['id_order']);

            // If current order state is not approved, block mail sending
            if ($order->getCurrentState() != Configuration::get('PS_OS_PAYMENT'))
                return false;
        }
    }

    public function getConfigForm($extensionOptions = true)
    {
        $form = require dirname(__FILE__) . '/config-form.php';

        return $extensionOptions ? MobbexHelper::executeHook('displayMobbexConfiguration', true, $form) : $form;
    }

    public function getSettings($extensionOptions = true)
    {
        $settings = [];

        $form = $this->getConfigForm($extensionOptions);

        foreach ($form['form']['input'] as $input) {
            $defaultValue  = isset($input['default']) ? $input['default'] : false;
            $settings[$input['name']] = isset($input['value']) ? $input['value'] : (Configuration::get($input['name']) ?: $defaultValue);
        }

        return $settings;
    }

    public function hookActionMobbexExpireOrder($orderId)
    {
        $order = new Order($orderId);

        // Exit if order cannot be loaded correctly
        if (!$order)
            return false;

        if ($order->getCurrentState() == Configuration::get(MobbexHelper::K_OS_PENDING))
            $order->setCurrentState((int) Configuration::get('PS_OS_CANCELED'));

        return true;
    }
}
        return $this->hookDisplayHeader();
    }

    /**
     * Support displayHeader hook aliases.
     */
    public function hookDisplayMobileHeader()
    {
        return $this->hookDisplayHeader();
    }

    public function hookActionEmailSendBefore($params)
    {
        if ($params['template'] == 'order_conf' && !empty($params['templateVars']['id_order'])) {
            $order = new Order($params['templateVars']['id_order']);

            // If current order state is not approved, block mail sending
            if ($order->getCurrentState() != Configuration::get('PS_OS_PAYMENT'))
                return false;
        }
    }

    public function getConfigForm($extensionOptions = true)
    {
        $form = require dirname(__FILE__) . '/config-form.php';

        return $extensionOptions ? MobbexHelper::executeHook('displayMobbexConfiguration', true, $form) : $form;
    }

    public function getSettings($extensionOptions = true)
    {
        $settings = [];

        $form = $this->getConfigForm($extensionOptions);

        foreach ($form['form']['input'] as $input) {
            $defaultValue  = isset($input['default']) ? $input['default'] : false;
            $settings[$input['name']] = isset($input['value']) ? $input['value'] : (Configuration::get($input['name']) ?: $defaultValue);
        }

        return $settings;
    }

    public function hookActionMobbexExpireOrder($orderId)
    {
        $order = new Order($orderId);

        // Exit if order cannot be loaded correctly
        if (!$order)
            return false;

        if ($order->getCurrentState() == Configuration::get(MobbexHelper::K_OS_PENDING))
            $order->setCurrentState((int) Configuration::get('PS_OS_CANCELED'));

        return true;
    }
}
