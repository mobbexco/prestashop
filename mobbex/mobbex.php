<?php

/**
 * mobbex.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 2.3.1
 * @see     PaymentModuleCore
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require dirname(__FILE__) . '/classes/Updater.php';
require dirname(__FILE__) . '/classes/MobbexHelper.php';
require dirname(__FILE__) . '/classes/MobbexTransaction.php';
require dirname(__FILE__) . '/classes/MobbexCustomFields.php';

/**
 * Main class of the module
 */
class Mobbex extends PaymentModule
{
    /** @var MobbexUpdater */
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
        $this->controllers = ['notification'];
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Mobbex');
        $this->description = $this->l('Plugin de pago utilizando Mobbex');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        // On 1.7.5 ignores the creation and finishes on an Fatal Error
        // Create the States if not exists because are really important
        $modules = PaymentModuleCore::getInstalledPaymentModules();
        foreach ($modules as $module) {
            // Check if the module is installed
            if ($module['name'] === $this->name) {
                $this->_createStates();
                break;
            }
        }

        // Only if you want to publish your module on the Addons Marketplace
        $this->module_key = 'mobbex_checkout';
        $this->updater = new MobbexUpdater();
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
        // Theme
        Configuration::updateValue(MobbexHelper::K_THEME, MobbexHelper::K_DEF_THEME);
        Configuration::updateValue(MobbexHelper::K_THEME_BACKGROUND, MobbexHelper::K_DEF_BACKGROUND);
        Configuration::updateValue(MobbexHelper::K_THEME_PRIMARY, MobbexHelper::K_DEF_PRIMARY);
        // Plans Widget
        Configuration::updateValue(MobbexHelper::K_PLANS, false);
        Configuration::updateValue(MobbexHelper::K_PLANS_TEXT, MobbexHelper::K_DEF_PLANS_TEXT);
        Configuration::updateValue(MobbexHelper::K_PLANS_TEXT_COLOR, MobbexHelper::K_DEF_PLANS_TEXT_COLOR);
        Configuration::updateValue(MobbexHelper::K_PLANS_BACKGROUND, MobbexHelper::K_DEF_PLANS_BACKGROUND);
        Configuration::updateValue(MobbexHelper::K_PLANS_IMAGE_URL, MobbexHelper::K_DEF_PLANS_IMAGE_URL);
        // DNI Fields
        Configuration::updateValue(MobbexHelper::K_OWN_DNI, false);
        Configuration::updateValue(MobbexHelper::K_CUSTOM_DNI, '');

        $this->_createTable();

        if (MobbexHelper::getPsVersion() === MobbexHelper::PS_16) {
            if (
                !parent::install()
                || !$this->registerHook('payment')
                || !$this->registerHook('paymentReturn')
                || !$this->registerHook('displayProductButtons')
                || !$this->registerHook('displayCustomerAccountForm')
                || !$this->registerHook('actionCustomerAccountAdd')
                || !$this->registerHook('displayAdminProductsExtra')
                || !$this->registerHook('actionProductUpdate')
                || !$this->registerHook('actionOrderStatusPostUpdate')
                || !$this->registerHook('displayBackOfficeCategory')
                || !$this->registerHook('categoryAddition')
                || !$this->registerHook('categoryUpdate')
                || !$this->registerHook('displayPDFInvoice')
                || !$this->registerHook('displayBackOfficeHeader')
                || !$this->registerHook('displayHeader')
            ) {
                return false;
            }
        } else {
            if (
                !parent::install()
                || !$this->registerHook('paymentOptions')
                || !$this->registerHook('paymentReturn')
                || !$this->registerHook('displayProductAdditionalInfo')
                || !$this->registerHook('additionalCustomerFormFields')
                || !$this->registerHook('actionObjectCustomerUpdateAfter')
                || !$this->registerHook('actionObjectCustomerAddAfter')
                || !$this->registerHook('displayAdminProductsExtra')
                || !$this->registerHook('actionProductUpdate')
                || !$this->registerHook('actionOrderStatusPostUpdate')
                || !$this->registerHook('displayBackOfficeCategory')
                || !$this->registerHook('categoryAddition')
                || !$this->registerHook('categoryUpdate')
                || !$this->registerHook('displayPDFInvoice')
                || !$this->registerHook('displayBackOfficeHeader')
                || !$this->registerHook('actionEmailSendBefore')
                || !$this->registerHook('displayHeader')
            ) {
                return false;
            }
        }

        return true;
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
        // IMPORTANT: States and tables will no longer be deleted
        // from database when the module is uninstalled.

        // Delete module config if option is sent
        if (isset($_COOKIE['mbbx_remove_config']) && $_COOKIE['mbbx_remove_config'] === 'true') {
            $form_values = $this->getConfigFormValues();
            foreach (array_keys($form_values) as $key) {
                Configuration::deleteByName($key);
            }
        }

        return parent::uninstall();
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

        if (!empty($_GET['run_update']))
            $this->runUpdate();

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

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Define the input of the configuration form
     *
     * @see $this->renderForm
     *
     * @return array
     */
    protected function getConfigForm()
    {

        return array(
            'form' => array(
                'tabs' => array(
                    'tab_general' => $this->l('General'),
                    'tab_appearence' => $this->l('Appearance'),
                    'tab_advanced' => $this->l('Advanced Configuration'),
                ),
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('API Key'),
                        'name' => MobbexHelper::K_API_KEY,
                        'required' => true,
                        'tab' => 'tab_general'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Access Token'),
                        'name' => MobbexHelper::K_ACCESS_TOKEN,
                        'required' => true,
                        'tab' => 'tab_general'
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Test Mode'),
                        'name' => MobbexHelper::K_TEST_MODE,
                        'is_bool' => true,
                        'required' => true,
                        'values' => [
                            [
                                'id' => 'active_on_mdv',
                                'value' => true,
                                'label' => $this->l('Test Mode'),
                            ],
                            [
                                'id' => 'active_off_mdv',
                                'value' => false,
                                'label' => $this->l('Live Mode'),
                            ],
                        ],
                        'tab' => 'tab_general'
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Theme Mode'),
                        'name' => MobbexHelper::K_THEME,
                        'is_bool' => false,
                        'required' => false,
                        'tab' => 'tab_appearence',
                        'values' => [
                            [
                                'id' => 'm_theme_light',
                                'value' => MobbexHelper::K_THEME_LIGHT,
                                'label' => $this->l('Light Mode'),
                            ],
                            [
                                'id' => 'm_theme_dark',
                                'value' => MobbexHelper::K_THEME_DARK,
                                'label' => $this->l('Dark Mode'),
                            ],
                        ],
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->l('Background Color'),
                        'name' => MobbexHelper::K_THEME_BACKGROUND,
                        'data-hex' => false,
                        'class' => 'mColorPicker',
                        'desc' => $this->l('Checkout Background Color'),
                        'tab' => 'tab_appearence',
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->l('Primary Color'),
                        'name' => MobbexHelper::K_THEME_PRIMARY,
                        'data-hex' => false,
                        'class' => 'mColorPicker',
                        'desc' => $this->l('Checkout Primary Color'),
                        'tab' => 'tab_appearence',
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Utilizar logo configurado en la tienda (prestashop)'),
                        'desc' => "Al desactivarse se utilizará el logo configurado en la cuenta de Mobbex.",
                        'name' => MobbexHelper::K_THEME_SHOP_LOGO,
                        'is_bool' => true,
                        'required' => true,
                        'tab' => 'tab_appearence',
                        'values' => [
                            [
                                'id' => 'active_on_shop_logo',
                                'value' => true,
                                'label' => $this->l('Activar'),
                            ],
                            [
                                'id' => 'active_off_shop_logo',
                                'value' => false,
                                'label' => $this->l('Desactivar'),
                            ],
                        ],
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Logo Personalizado ( URL )'),
                        'name' => MobbexHelper::K_THEME_LOGO,
                        'required' => false,
                        'desc' => "Opcional. Debe utilizar la URL completa y debe ser HTTPS. Sólo configure su logo si es necesario que no se utilice el logo de su cuenta en Mobbex. Dimensiones: 250x250 píxeles. El Logo debe ser cuadrado para optimización.",
                        'tab' => 'tab_appearence',
                    ),
                    // Embed SDK
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Experiencia de Pago en el Sitio'),
                        'name' => MobbexHelper::K_EMBED,
                        'is_bool' => true,
                        'required' => true,
                        'tab' => 'tab_general',
                        'values' => [
                            [
                                'id' => 'active_on_embed',
                                'value' => true,
                                'label' => $this->l('Activar'),
                            ],
                            [
                                'id' => 'active_off_embed',
                                'value' => false,
                                'label' => $this->l('Desactivar'),
                            ],
                        ],
                    ),
                    // Wallet
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Mobbex Wallet para usuarios logeados'),
                        'name' => MobbexHelper::K_WALLET,
                        'is_bool' => true,
                        'required' => true,
                        'tab' => 'tab_general',
                        'values' => [
                            [
                                'id' => 'active_on_wallet',
                                'value' => true,
                                'label' => $this->l('Activar'),
                            ],
                            [
                                'id' => 'active_off_wallet',
                                'value' => false,
                                'label' => $this->l('Desactivar'),
                            ],
                        ],
                    ),
                    // Reseller ID
                    array(
                        'type' => 'text',
                        'label' => $this->l('ID o Clave de Revendedor'),
                        'name' => MobbexHelper::K_RESELLER_ID,
                        'required' => false,
                        'tab' => 'tab_advanced',
                        'desc' => "Ingrese este identificador sólo si se es parte de un programa de reventas. El identificador NO debe tener espacios, solo letras, números o guiones. El identificador se agregará a la referencia de Pago para identificar su venta.",
                    ),
                    // Plans
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Widget de planes'),
                        'name' => MobbexHelper::K_PLANS,
                        'is_bool' => true,
                        'required' => true,
                        'values' => [
                            [
                                'id' => 'active_on_plans',
                                'value' => true,
                                'label' => $this->l('Activar'),
                            ],
                            [
                                'id' => 'active_off_plans',
                                'value' => false,
                                'label' => $this->l('Desactivar'),
                            ],
                        ],
                        'tab' => 'tab_general',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Imagen del botón de financiación ( URL )'),
                        'name' => MobbexHelper::K_PLANS_IMAGE_URL,
                        'required' => false,
                        'desc' => $this->l('Opcional. Debe utilizar la URL completa y debe ser HTTPS.'),
                        'tab' => 'tab_appearence',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Plans Button Text'),
                        'name' => MobbexHelper::K_PLANS_TEXT,
                        'required' => false,
                        'desc' => $this->l('Optional. Text displayed on finnancing button'),
                        'tab' => 'tab_appearence',
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->l('Text Color'),
                        'name' => MobbexHelper::K_PLANS_TEXT_COLOR,
                        'data-hex' => false,
                        'class' => 'mColorPicker',
                        'desc' => $this->l('Plans Button Text Color'),
                        'tab' => 'tab_appearence',
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->l('Background Color'),
                        'name' => MobbexHelper::K_PLANS_BACKGROUND,
                        'data-hex' => false,
                        'class' => 'mColorPicker',
                        'desc' => $this->l('Plans Button Background Color'),
                        'tab' => 'tab_appearence',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Padding'),
                        'name' => MobbexHelper::K_PLANS_PADDING,
                        'required' => false,
                        'desc' => $this->l('Plans Button Padding'),
                        'tab' => 'tab_appearence',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Font-Size'),
                        'name' => MobbexHelper::K_PLANS_FONT_SIZE,
                        'required' => false,
                        'desc' => $this->l('Plans Button Font-Size (Ej: 5px)'),
                        'tab' => 'tab_appearence',
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Plans Module Theme Mode'),
                        'name' => MobbexHelper::K_PLANS_THEME,
                        'is_bool' => false,
                        'required' => false,
                        'tab' => 'tab_appearence',
                        'values' => [
                            [
                                'id' => 'm_plans_theme_light',
                                'value' => MobbexHelper::K_THEME_LIGHT,
                                'label' => $this->l('Light Mode'),
                            ],
                            [
                                'id' => 'm_plans_theme_dark',
                                'value' => MobbexHelper::K_THEME_DARK,
                                'label' => $this->l('Dark Mode'),
                            ],
                        ],
                    ),
                    // DNI
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Agregar campo DNI'),
                        'name' => MobbexHelper::K_OWN_DNI,
                        'is_bool' => true,
                        'required' => true,
                        'tab' => 'tab_general',
                        'values' => [
                            [
                                'id' => 'active_on_own_dni',
                                'value' => true,
                                'label' => $this->l('Activar'),
                            ],
                            [
                                'id' => 'active_off_own_dni',
                                'value' => false,
                                'label' => $this->l('Desactivar'),
                            ],
                        ],
                    ),
                    //Multicard
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Permite el uso de multiples tarjetas'),
                        'name' => MobbexHelper::K_MULTICARD, //?
                        'is_bool' => true,
                        'required' => false,
                        'tab' => 'tab_advanced',
                        'values' => [
                            [
                                'id' => 'active_on_multicard',
                                'value' => true,
                                'label' => $this->l('Activar'),
                            ],
                            [
                                'id' => 'active_off_multicard',
                                'value' => false,
                                'label' => $this->l('Desactivar'),
                            ],
                        ],
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Usar campo DNI existente'),
                        'name' => MobbexHelper::K_CUSTOM_DNI,
                        'required' => false,
                        'tab' => 'tab_general',
                        'desc' => "Si ya solicita el campo DNI al finalizar la compra o al registrarse, proporcione el nombre del campo personalizado.",
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Retrieve the current configuration values.
     *
     * @see $this->renderForm
     *
     * @return array
     */
    protected function getConfigFormValues()
    {
        return array(
            MobbexHelper::K_API_KEY => Configuration::get(MobbexHelper::K_API_KEY, ''),
            MobbexHelper::K_ACCESS_TOKEN => Configuration::get(MobbexHelper::K_ACCESS_TOKEN, ''),
            MobbexHelper::K_TEST_MODE => Configuration::get(MobbexHelper::K_TEST_MODE, false),
            MobbexHelper::K_EMBED => Configuration::get(MobbexHelper::K_EMBED, false),
            MobbexHelper::K_WALLET => Configuration::get(MobbexHelper::K_WALLET, false),
            // Theme
            MobbexHelper::K_THEME => Configuration::get(MobbexHelper::K_THEME, MobbexHelper::K_DEF_THEME),
            MobbexHelper::K_THEME_BACKGROUND => Configuration::get(MobbexHelper::K_THEME_BACKGROUND, MobbexHelper::K_DEF_BACKGROUND),
            MobbexHelper::K_THEME_PRIMARY => Configuration::get(MobbexHelper::K_THEME_PRIMARY, MobbexHelper::K_DEF_PRIMARY),
            MobbexHelper::K_THEME_LOGO => Configuration::get(MobbexHelper::K_THEME_LOGO, ''),
            MobbexHelper::K_THEME_SHOP_LOGO => Configuration::get(MobbexHelper::K_THEME_SHOP_LOGO, ''),
            // Reseller ID
            MobbexHelper::K_RESELLER_ID => Configuration::get(MobbexHelper::K_RESELLER_ID, ''),
            // Plans Widget
            MobbexHelper::K_PLANS => Configuration::get(MobbexHelper::K_PLANS, false),
            MobbexHelper::K_PLANS_TEXT => Configuration::get(MobbexHelper::K_PLANS_TEXT, MobbexHelper::K_DEF_PLANS_TEXT),
            MobbexHelper::K_PLANS_TEXT_COLOR => Configuration::get(MobbexHelper::K_PLANS_TEXT_COLOR, MobbexHelper::K_DEF_PLANS_TEXT_COLOR),
            MobbexHelper::K_PLANS_BACKGROUND => Configuration::get(MobbexHelper::K_PLANS_BACKGROUND, MobbexHelper::K_DEF_PLANS_BACKGROUND),
            MobbexHelper::K_PLANS_IMAGE_URL => Configuration::get(MobbexHelper::K_PLANS_IMAGE_URL, MobbexHelper::K_DEF_PLANS_IMAGE_URL),
            MobbexHelper::K_PLANS_PADDING => Configuration::get(MobbexHelper::K_PLANS_PADDING, MobbexHelper::K_DEF_PLANS_PADDING),
            MobbexHelper::K_PLANS_FONT_SIZE => Configuration::get(MobbexHelper::K_PLANS_FONT_SIZE, MobbexHelper::K_DEF_PLANS_FONT_SIZE),
            MobbexHelper::K_PLANS_THEME => Configuration::get(MobbexHelper::K_PLANS_THEME, MobbexHelper::K_DEF_PLANS_THEME),
            // DNI Fields
            MobbexHelper::K_OWN_DNI => Configuration::get(MobbexHelper::K_OWN_DNI, false),
            MobbexHelper::K_CUSTOM_DNI => Configuration::get(MobbexHelper::K_CUSTOM_DNI, ''),
            //Multicard field
            MobbexHelper::K_MULTICARD => Configuration::get(MobbexHelper::K_MULTICARD, false),
            // IMPORTANT! Do not add Order States here. These values are used to save form fields
        );
    }

    public function _createTable()
    {
        DB::getInstance()->execute(
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "mobbex_transaction` (
                `cart_id` INT(11) NOT NULL,
				`data` TEXT NOT NULL,
				PRIMARY KEY (`cart_id`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;"
        );

        DB::getInstance()->execute(
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "mobbex_custom_fields` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `row_id` INT(11) NOT NULL,
				`object` TEXT NOT NULL,
				`field_name` TEXT NOT NULL,
				`data` TEXT NOT NULL,
				PRIMARY KEY (`id`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;"
        );
    }

    private function _createStates()
    {
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
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        $this->createIdentificationColumn();
    }

    public function createIdentificationColumn()
    {
        $own_dni = Configuration::get(MobbexHelper::K_OWN_DNI);
        $custom_dni = Configuration::get(MobbexHelper::K_CUSTOM_DNI);

        if ($custom_dni != '') {
            // Check if column exists
            $table_columns = DB::getInstance()->executeS("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "customer` LIKE '" . $custom_dni . "'");

            if (!empty($table_columns)) {
                // If both options are active at the same time, custom_dni takes precedence
                if ($own_dni) {
                    Configuration::updateValue(MobbexHelper::K_OWN_DNI, false);
                    $own_dni = false;
                }
                return;
            }

            Configuration::updateValue(MobbexHelper::K_CUSTOM_DNI, '');
        }

        if ($own_dni) {
            // Check if column exists
            $table_columns = DB::getInstance()->executeS("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "customer` LIKE 'billing_dni'");

            if (!empty($table_columns)) {
                return;
            }
            return DB::getInstance()->execute(
                "ALTER TABLE `" . _DB_PREFIX_ . "customer` ADD `billing_dni` varchar(255);"
            );
        }
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
            $trx = MobbexTransaction::getTransaction($order->id_cart);

            // Assign the Data into Smarty
            $this->smarty->assign('status', $order->getCurrentStateFull($this->context->language->id)['name']);
            $this->smarty->assign('total', $trx['payment']['total']);
            $this->smarty->assign('payment', $order->payment);
            $this->smarty->assign('mobbex_data', $trx);
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

        $options      = [];
        $checkoutData = MobbexHelper::getPaymentData();
        $cards        = isset($checkoutData['wallet']) ? $checkoutData['wallet'] : [];

        MobbexHelper::addJavascriptData([
            'embed'       => (bool) Configuration::get(MobbexHelper::K_EMBED),
            'wallet'      => isset($checkoutData['wallet']) ? $checkoutData['wallet'] : null,
            'checkoutId'  => $checkoutData['id'],
            'checkoutUrl' => $checkoutData['url'],
            'returnUrl'   => $checkoutData['return_url']
        ]);

        $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($this->l('Pagar utilizando tarjetas, efectivo u otros'))
            ->setForm($this->context->smarty->fetch('module:mobbex/views/templates/front/payment.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . 'mobbex/views/img/logo_transparent.png'));

        $options[] = $option;

        foreach ($cards as $key => $card) {
            $this->context->smarty->assign([
                'card' => $card,
                'key'  => $key
            ]);

            $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $option->setCallToActionText($card['name'])
                ->setForm($this->context->smarty->fetch('module:mobbex/views/templates/front/card-form.tpl'))
                ->setLogo($card['source']['card']['product']['logo']);
    
            $options[] = $option;
        }

        return $options;
    }

    public function hookDisplayProductAdditionalInfo($params)
    {
        $product = new Product(Tools::getValue('id_product'));
        if (!Configuration::get(MobbexHelper::K_PLANS) || !Validate::isLoadedObject($product) || !($product->show_price)) {
            return;
        }

        $image_url = 'https://res.mobbex.com/images/sources/mobbex.png';
        if (Configuration::get(MobbexHelper::K_PLANS_IMAGE_URL)) {
            $image_url = trim(Configuration::get(MobbexHelper::K_PLANS_IMAGE_URL));
        }

        $total = $product->getPrice(); 

        //Get product and category plans
        $active_plans = MobbexHelper::getActivePlans($product);
        $inactive_plans = MobbexHelper::getInactivePlans($product);

        //get sources
        $sources = MobbexHelper::getSources($total, $inactive_plans);
        $sources_advanced = MobbexHelper::getSourcesAdvanced();
        $sources_advanced = MobbexHelper::filterAdvancedSources($sources_advanced, $active_plans);
        $sources = MobbexHelper::mergeSources($sources, $sources_advanced);

        error_log('Var: ' . json_encode($active_plans, JSON_PRETTY_PRINT) . "/n", 3, 'log.log');
        error_log('Var: ' . json_encode($sources_advanced, JSON_PRETTY_PRINT) . "/n", 3, 'log.log');
        error_log('Var: ' . json_encode($sources, JSON_PRETTY_PRINT) . "/n", 3, 'log.log');
        
        $this->context->smarty->assign(
            [
                'product_price' => $total,
                'sources' => $sources,
                'style_settings' =>
                [
                    'text' => Configuration::get(MobbexHelper::K_PLANS_TEXT, 'Planes Mobbex'),
                    'text_color' => Configuration::get(MobbexHelper::K_PLANS_TEXT_COLOR, '#ffffff'),
                    'background' => Configuration::get(MobbexHelper::K_PLANS_BACKGROUND, '#8900ff'),
                    'button_image' => $image_url,
                    'button_padding' => Configuration::get(MobbexHelper::K_PLANS_PADDING, '4px 18px'),
                    'button_font_size' => Configuration::get(MobbexHelper::K_PLANS_FONT_SIZE, '16px'),
                    'plans_theme' => Configuration::get(MobbexHelper::K_PLANS_THEME, 'light'),
                ],
            ]
        );

        return $this->display(__FILE__, 'views/templates/hooks/plans.tpl');
    }

    public function hookAdditionalCustomerFormFields($params)
    {
        if (!Configuration::get(MobbexHelper::K_OWN_DNI, false) || Configuration::get(MobbexHelper::K_CUSTOM_DNI, '') != '') {
            return;
        }
        $customer = Context::getContext()->customer;

        $dni_field = array();
        $dni_field['billing_dni'] = (new FormField)
            ->setName('billing_dni')
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
        if (!Configuration::get(MobbexHelper::K_OWN_DNI, false) || empty($params['object']->id) || empty($_POST['billing_dni']) || Configuration::get(MobbexHelper::K_CUSTOM_DNI, '') != '') {
            return;
        }

        $customer_id = $params['object']->id;
        $billing_dni = $_POST['billing_dni'];

        return DB::getInstance()->execute(
            "UPDATE `" . _DB_PREFIX_ . "customer` SET billing_dni = $billing_dni WHERE `id_customer` = $customer_id;"
        );
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

        Media::addJsDef([
            'mbbx' => [
                'embed'       => (bool) Configuration::get(MobbexHelper::K_EMBED),
                'wallet'      => isset($checkoutData['wallet']) ? $checkoutData['wallet'] : null,
                'checkoutId'  => $checkoutData['id'],
                'checkoutUrl' => $checkoutData['url'],
                'returnUrl'   => $checkoutData['return_url']
                ]
        ]);

        $this->context->smarty->assign(['cards' => isset($checkoutData['wallet']) ? $checkoutData['wallet'] : []]);

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
        return $this->hookDisplayProductAdditionalInfo(null);
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

    /**
     * Is trigger when the state of an order is change, and works only if it is a mobbex transaction
     * it first get the transaction_id from mobbex_transaction table, later the id is going to be use
     * to call the API
     * 
     * Support for 1.6 - 1.7
     *
     * @return string
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $idRefunded = (int)Configuration::get('PS_OS_REFUND'); //get id of refunded state
        $order = new Order($params['id_order']);
        if ($params['newOrderStatus']->id == $idRefunded && $order->module == 'mobbex') {
            $transactionData = MobbexTransaction::getTransaction($order->id_cart);
            $response = MobbexHelper::porcessRefund($transactionData['payment']['id']);
            return $response;
        }
        return false; //not a mobbex transaction
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
        $this->context->smarty->assign(MobbexHelper::getPlansFilterFields($params['id_product'] ?: Tools::getValue('id_product')));

        return $this->display(__FILE__, 'views/templates/hooks/product-settings.tpl');
    }

    /**
     * Show category admin settings.
     * 
     * @param array $params
     */
    public function hookDisplayBackOfficeCategory($params)
    {
        $this->context->smarty->assign(MobbexHelper::getPlansFilterFields(Tools::getValue('id_category'), 'category'));

        return $this->display(__FILE__, 'views/templates/hooks/category-settings.tpl');
    }

    public function hookActionProductUpdate($params)
    {
        $commonPlans = $advancedPlans = [];

        // Get plans selected
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'common_plan_') !== false && $value === 'no') {
                // Add UID to common plans
                $commonPlans[] = explode('common_plan_', $key)[1];
            } else if (strpos($key, 'advanced_plan_') !== false && $value === 'yes'){
                // Add UID to advanced plans
                $advancedPlans[] = explode('advanced_plan_', $key)[1];
            }
        }

        // If is bulk import
        if (strnatcasecmp(Tools::getValue('controller'), 'adminImport') === 0) {
            // Only save when they are not empty
            if (!empty($commonPlans))
                MobbexCustomFields::saveCustomField($params['id_product'], 'product', 'common_plans', json_encode($commonPlans));
            if (!empty($advancedPlans))
                MobbexCustomFields::saveCustomField($params['id_product'], 'product', 'advanced_plans', json_encode($advancedPlans));
        } else {
            // Save data directly
            MobbexCustomFields::saveCustomField($params['id_product'], 'product', 'common_plans', json_encode($commonPlans));
            MobbexCustomFields::saveCustomField($params['id_product'], 'product', 'advanced_plans', json_encode($advancedPlans));
        }
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

        // Get plans selected
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'common_plan_') !== false && $value === 'no') {
                // Add UID to common plans
                $commonPlans[] = explode('common_plan_', $key)[1];
            } else if (strpos($key, 'advanced_plan_') !== false && $value === 'yes'){
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
        } else {
            // Save data directly
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
     */
    public function hookDisplayHeader()
    {
        $currentPage = Tools::getValue('controller');
        $mediaPath   = Media::getMediaPath(_PS_MODULE_DIR_ . $this->name);

        // Checkout page
        if ($currentPage == 'order' && MobbexHelper::isPaymentStep()) {
            $this->context->controller->addCSS("$mediaPath/views/css/front.css");

            MobbexHelper::addScript("$mediaPath/views/js/front.js", true);

            if (Configuration::get(MobbexHelper::K_WALLET))
                MobbexHelper::addScript('https://res.mobbex.com/js/sdk/mobbex@1.1.0.js', true);

            if (Configuration::get(MobbexHelper::K_EMBED))
                MobbexHelper::addScript('https://res.mobbex.com/js/embed/mobbex.embed@1.0.20.js', true);
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
            $this->context->controller->addJS("$mediaPath/views/js/uninstall-options.js");

        // Configuration page
        if ($currentPage == 'AdminModules' && Tools::getValue('configure') == 'mobbex') {
            $this->context->controller->addJS("$mediaPath/views/js/mobbex-config.js");

            // If plugin has updates, add update data to javascript
            if ($this->updater->hasUpdates(MobbexHelper::MOBBEX_VERSION))
                MobbexHelper::addJavascriptData(['updateVersion' => $this->updater->latestRelease['tag_name']]);
        }
    }

    public function hookActionEmailSendBefore($params)
    {
        if ($params['template'] == 'order_conf' && !empty(MobbexHelper::$transactionData['status'])) {
            $state = MobbexHelper::getState(MobbexHelper::$transactionData['status']);

            // If current order state is not approved, block mail sending
            if ($state != 'approved')
                return false;
        }
    }
}
