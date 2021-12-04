<?php

/**
 * mobbex.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 2.6.5
 * @see     PaymentModuleCore
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/Exception.php';
require_once dirname(__FILE__) . '/classes/Api.php';
require_once dirname(__FILE__) . '/classes/Updater.php';
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
        $this->controllers = ['notification', 'redirect'];
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
        $this->updater = new \Mobbex\Updater();

        $this->addExtensionHooks();
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

        return parent::install() && $this->registerHooks();
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
            $form = $this->getConfigForm();

            foreach ($form['form']['input'] as $input) {
                Configuration::deleteByName($input['name']);
            }
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
            'actionProductUpdate',
            'displayBackOfficeCategory',
            'categoryAddition',
            'categoryUpdate',
            'displayPDFInvoice',
            'displayBackOfficeHeader',
            'displayHeader',
            'paymentReturn',
            'actionOrderReturn',
            'displayAdminOrder'
        ];

        $ps16Hooks = [
            'payment',
            'displayProductButtons',
            'displayCustomerAccountForm',
            'actionCustomerAccountAdd',
        ];

        $ps17Hooks = [
            'paymentOptions',
            'displayProductAdditionalInfo',
            'additionalCustomerFormFields',
            'actionObjectCustomerUpdateAfter',
            'actionObjectCustomerAddAfter',
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
     * Create own hooks to extend features in external modules.
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
                $hook->add();
            }
        }
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

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues($form),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm([$form]);
    }

    /**
     * Define the input of the configuration form
     *
     * @param bool $extensionOptions Include options added by extensions.
     * 
     * @see $this->renderForm
     *
     * @return array
     */
    protected function getConfigForm($extensionOptions = false)
    {
        $form = [
            'form' => [
                'tabs' => [
                    'tab_general' => $this->l('General'),
                    'tab_appearence' => $this->l('Appearance'),
                    'tab_advanced' => $this->l('Advanced Configuration'),
                ],
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ]
            ],
        ];

        if (MobbexHelper::needUpgrade())
            $form['form']['warning'] = 'Actualice la base de datos desde <a href="' . MobbexHelper::getUpgradeURL() . '">aquí</a> para que el módulo funcione correctamente.';

        if ($this->updater->hasUpdates(MobbexHelper::MOBBEX_VERSION))
            $form['form']['description'] = "¡Nueva actualización disponible! Haga <a href='$_SERVER[REQUEST_URI]&run_update=1'>clic aquí</a> para actualizar a la versión " . $this->updater->latestRelease['tag_name'];

        $form['form']['input'] = [
            [
                'type' => 'text',
                'label' => $this->l('API Key'),
                'name' => MobbexHelper::K_API_KEY,
                'required' => true,
                'tab' => 'tab_general'
            ],
            [
                'type' => 'text',
                'label' => $this->l('Access Token'),
                'name' => MobbexHelper::K_ACCESS_TOKEN,
                'required' => true,
                'tab' => 'tab_general'
            ],
            [
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
            ],
            [
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
                'default' => MobbexHelper::K_DEF_THEME,
            ],
            [
                'type' => 'color',
                'label' => $this->l('Background Color'),
                'name' => MobbexHelper::K_THEME_BACKGROUND,
                'data-hex' => false,
                'class' => 'mColorPicker',
                'desc' => $this->l('Checkout Background Color'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_BACKGROUND,
            ],
            [
                'type' => 'color',
                'label' => $this->l('Primary Color'),
                'name' => MobbexHelper::K_THEME_PRIMARY,
                'data-hex' => false,
                'class' => 'mColorPicker',
                'desc' => $this->l('Checkout Primary Color'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PRIMARY,
            ],
            [
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
            ],
            [
                'type' => 'text',
                'label' => $this->l('Logo Personalizado ( URL )'),
                'name' => MobbexHelper::K_THEME_LOGO,
                'required' => false,
                'desc' => "Opcional. Debe utilizar la URL completa y debe ser HTTPS. Sólo configure su logo si es necesario que no se utilice el logo de su cuenta en Mobbex. Dimensiones: 250x250 píxeles. El Logo debe ser cuadrado para optimización.",
                'tab' => 'tab_appearence',
            ],
            // Embed SDK
            [
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
            ],
            // Wallet
            [
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
            ],
            // Reseller ID
            [
                'type' => 'text',
                'label' => $this->l('ID o Clave de Revendedor'),
                'name' => MobbexHelper::K_RESELLER_ID,
                'required' => false,
                'tab' => 'tab_advanced',
                'desc' => "Ingrese este identificador sólo si se es parte de un programa de reventas. El identificador NO debe tener espacios, solo letras, números o guiones. El identificador se agregará a la referencia de Pago para identificar su venta.",
            ],
            // Plans
            [
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
            ],
            [
                'type' => 'text',
                'label' => $this->l('Imagen del botón de financiación ( URL )'),
                'name' => MobbexHelper::K_PLANS_IMAGE_URL,
                'required' => false,
                'desc' => $this->l('Opcional. Debe utilizar la URL completa y debe ser HTTPS.'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PLANS_IMAGE_URL,
            ],
            [
                'type' => 'text',
                'label' => $this->l('Plans Button Text'),
                'name' => MobbexHelper::K_PLANS_TEXT,
                'required' => false,
                'desc' => $this->l('Optional. Text displayed on finnancing button'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PLANS_TEXT,
            ],
            [
                'type' => 'color',
                'label' => $this->l('Text Color'),
                'name' => MobbexHelper::K_PLANS_TEXT_COLOR,
                'data-hex' => false,
                'class' => 'mColorPicker',
                'desc' => $this->l('Plans Button Text Color'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PLANS_TEXT_COLOR,
            ],
            [
                'type' => 'color',
                'label' => $this->l('Background Color'),
                'name' => MobbexHelper::K_PLANS_BACKGROUND,
                'data-hex' => false,
                'class' => 'mColorPicker',
                'desc' => $this->l('Plans Button Background Color'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PLANS_BACKGROUND,
            ],
            [
                'type' => 'text',
                'label' => $this->l('Padding'),
                'name' => MobbexHelper::K_PLANS_PADDING,
                'required' => false,
                'desc' => $this->l('Plans Button Padding'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PLANS_PADDING,
            ],
            [
                'type' => 'text',
                'label' => $this->l('Font-Size'),
                'name' => MobbexHelper::K_PLANS_FONT_SIZE,
                'required' => false,
                'desc' => $this->l('Plans Button Font-Size (Ej: 5px)'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PLANS_FONT_SIZE,
            ],
            [
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
                'default' => MobbexHelper::K_DEF_PLANS_THEME,
            ],
            // DNI
            [
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
            ],
            //Multicard
            [
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
            ],
            //Multivendor
            [
                'type'     => 'select',
                'label'    => $this->l('Multivendedor'),
                'desc'     => $this->l('Permite el uso de múltiples vendedores (hasta 4 entidades diferentes)'),
                'name'     => MobbexHelper::K_MULTIVENDOR,
                'required' => false,
                'tab'      => 'tab_advanced',
                'options'  => [
                    'query' => [
                        [
                            'id_option' => false,
                            'name'      => 'Desactivado'
                        ],
                        [
                            'id_option' => 'unified',
                            'name'      => 'Unificado'
                        ],
                        [
                            'id_option' => 'active',
                            'name'      => 'Activado'
                        ],
                    ],
                    'id'   => 'id_option',
                    'name' => 'name'
                ]
            ],
            [
                'type' => 'text',
                'label' => $this->l('Usar campo DNI existente'),
                'name' => MobbexHelper::K_CUSTOM_DNI,
                'required' => false,
                'tab' => 'tab_general',
                'desc' => "Si ya solicita el campo DNI al finalizar la compra o al registrarse, proporcione el nombre del campo personalizado.",
            ],
            // Unified method
            [
                'type' => 'switch',
                'label' => $this->l('Modo unificado'),
                'desc' => $this->l('Deshabilita la subdivisión de los métodos de pago en la página de finalización de la compra. Las opciones se verán dentro del checkout.'),
                'name' => MobbexHelper::K_UNIFIED_METHOD,
                'is_bool' => true,
                'required' => false,
                'tab' => 'tab_advanced',
                'values' => [
                    [
                        'id' => 'active_on_unified_method',
                        'value' => true,
                        'label' => $this->l('Activar'),
                    ],
                    [
                        'id' => 'active_off_unified_method',
                        'value' => false,
                        'label' => $this->l('Desactivar'),
                    ],
                ],
            ],
            // Debug mode
            [
                'type' => 'switch',
                'label' => $this->l('Modo Debug'),
                'name' => MobbexHelper::K_DEBUG,
                'is_bool' => true,
                'required' => false,
                'tab' => 'tab_advanced',
                'values' => [
                    [
                        'id' => 'active_on_debug',
                        'value' => true,
                        'label' => $this->l('Activar'),
                    ],
                    [
                        'id' => 'active_off_debug',
                        'value' => false,
                        'label' => $this->l('Desactivar'),
                    ],
                ],
            ],
        ];

        return $extensionOptions ? MobbexHelper::executeHook('displayMobbexConfiguration', true, $form) : $form;
    }

    /**
     * Retrieve the current configuration values.
     *
     * @see $this->renderForm
     *
     * @return array
     */
    protected function getConfigFormValues($form)
    {
        $values = [];

        foreach ($form['form']['input'] as $input) {
            $defaultValue  = isset($input['default']) ? $input['default'] : false;
            $values[$input['name']] = isset($input['value']) ? $input['value'] : (Configuration::get($input['name']) ?: $defaultValue);
        }

        return $values;
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
                    `id` INT(11) NOT NULL PRIMARY KEY,
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
                    `updated` TEXT NOT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;"
            );
        }

        $db->execute(
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

    public function _alterTable()
    {
        DB::getInstance()->execute(
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
        $form = $this->getConfigForm(true);

        foreach ($form['form']['input'] as $input) {
            Configuration::updateValue($input['name'], Tools::getValue($input['name']));
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

        MobbexHelper::addJavascriptData([
            'embed'     => (bool) Configuration::get(MobbexHelper::K_EMBED),
            'wallet'    => $cards ?: null,
            'id'        => $checkoutData['id'],
            'sid'       => isset($checkoutData['sid']) ? $checkoutData['sid'] : null,
            'url'       => $checkoutData['url'],
            'returnUrl' => $checkoutData['return_url']
        ]);

        // Get payment methods from checkout
        if (Configuration::get(MobbexHelper::K_UNIFIED_METHOD)) {
            $options[] = $this->createPaymentOption(
                $this->l('Pagar utilizando tarjetas, efectivo u otros'),
                Media::getMediaPath(_PS_MODULE_DIR_ . 'mobbex/views/img/logo_transparent.png'),
                'module:mobbex/views/templates/front/payment.tpl',
                ['checkoutUrl' => MobbexHelper::getModuleUrl('redirect', '', '&checkout_url=' . urlencode($checkoutData['url']))]
            );
        } else {
            foreach ($methods as $method) {
                $checkoutUrl = MobbexHelper::getModuleUrl('redirect', '', '&checkout_url=' . urlencode($checkoutData['url'] . "?paymentMethod={$method['group']}:{$method['subgroup']}"));

                $options[] = $this->createPaymentOption(
                    $method['subgroup_title'],
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
                $card['source']['card']['product']['logo'],
                'module:mobbex/views/templates/front/card-form.tpl',
                compact('card', 'key')
            );
        }

        return $options;
    }

    public function createPaymentOption($title, $logo, $template, $templateVars = null)
    {
        if ($templateVars)
            $this->context->smarty->assign($templateVars);

        $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($title)
            ->setForm($this->context->smarty->fetch($template))
            ->setLogo($logo);

        return $option;
    }

    public function hookDisplayProductAdditionalInfo()
    {
        $product = new Product(Tools::getValue('id_product'));

        if (!Configuration::get(MobbexHelper::K_PLANS) || !Validate::isLoadedObject($product) || !$product->show_price)
            return;

        $this->context->smarty->assign([
            'product_price'  => number_format($product->getPrice(), 2),
            'sources'        => MobbexHelper::getSources($product->getPrice(), MobbexHelper::getInstallments([$product])),
            'style_settings' => [
                'text'             => Configuration::get(MobbexHelper::K_PLANS_TEXT, 'Planes Mobbex'),
                'text_color'       => Configuration::get(MobbexHelper::K_PLANS_TEXT_COLOR, '#ffffff'),
                'background'       => Configuration::get(MobbexHelper::K_PLANS_BACKGROUND, '#8900ff'),
                'button_image'     => Configuration::get(MobbexHelper::K_PLANS_IMAGE_URL) ?: 'https://res.mobbex.com/images/sources/mobbex.png',
                'button_padding'   => Configuration::get(MobbexHelper::K_PLANS_PADDING, '4px 18px'),
                'button_font_size' => Configuration::get(MobbexHelper::K_PLANS_FONT_SIZE, '16px'),
                'plans_theme'      => Configuration::get(MobbexHelper::K_PLANS_THEME, 'light'),
            ],
        ]);

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
                'embed'     => (bool) Configuration::get(MobbexHelper::K_EMBED),
                'wallet'    => isset($checkoutData['wallet']) ? $checkoutData['wallet'] : null,
                'id'        => $checkoutData['id'],
                'sid'       => isset($checkoutData['sid']) ? $checkoutData['sid'] : null,
                'url'       => $checkoutData['url'],
                'returnUrl' => $checkoutData['return_url']
            ]
        ]);

        $this->context->smarty->assign([
            'methods' => isset($checkoutData['paymentMethods']) && !Configuration::get(MobbexHelper::K_UNIFIED_METHOD) ? $checkoutData['paymentMethods'] : [],
            'cards'   => isset($checkoutData['wallet']) ? $checkoutData['wallet'] : []
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
        $id = !empty($params['id_product']) ? $params['id_product'] : Tools::getValue('id_product');

        $this->context->smarty->assign([
            'id'     => $id,
            'plans'  => MobbexHelper::getPlansFilterFields($id),
            'entity' => MobbexCustomFields::getCustomField($id, 'product', 'entity') ?: ''
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
        $id = !empty($params['id_category']) ? $params['id_category'] : Tools::getValue('id_category');

        $this->context->smarty->assign([
            'id'     => $id,
            'plans'  => MobbexHelper::getPlansFilterFields($id, 'category'),
            'entity' => MobbexCustomFields::getCustomField($id, 'category', 'entity') ?: ''
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
        $trx = MobbexTransaction::getTransactions($order->id_cart, true);

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
                'sources'  => MobbexHelper::getWebhookSources(MobbexTransaction::getTransactions($order->id_cart)),
                'entities' => MobbexHelper::getWebhookEntities(MobbexTransaction::getTransactions($order->id_cart))
            ]
        );

        return $this->display(__FILE__, 'views/templates/hooks/order-widget.tpl');
    }

    public function hookActionProductUpdate($params)
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
                MobbexCustomFields::saveCustomField($params['id_product'], 'product', 'common_plans', json_encode($commonPlans));
            if (!empty($advancedPlans))
                MobbexCustomFields::saveCustomField($params['id_product'], 'product', 'advanced_plans', json_encode($advancedPlans));
            if ($entity)
                MobbexCustomFields::saveCustomField($params['id_product'], 'product', 'entity', $entity);
        } else {
            // Save data directly
            MobbexCustomFields::saveCustomField($params['id_product'], 'product', 'entity', $entity);
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
     */
    public function hookDisplayHeader()
    {
        $currentPage = Tools::getValue('controller');
        $mediaPath   = Media::getMediaPath(_PS_MODULE_DIR_ . $this->name);

        // Checkout page
        if ($currentPage == 'order') {
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

            // If plugin has updates, add update data to javascript
            if ($this->updater->hasUpdates(MobbexHelper::MOBBEX_VERSION))
                MobbexHelper::addJavascriptData(['updateVersion' => $this->updater->latestRelease['tag_name']]);
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
            $order = new Order($params['templateVars']['id_order']);

            // If current order state is not approved, block mail sending
            if ($order->getCurrentState() != Configuration::get('PS_OS_PAYMENT'))
                return false;
        }
    }
}
