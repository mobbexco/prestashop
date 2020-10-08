<?php
/**
 * mobbex.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 1.4.9
 * @see     PaymentModuleCore
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require dirname(__FILE__) . '/classes/MobbexHelper.php';
require dirname(__FILE__) . '/classes/MobbexTransaction.php';

/**
 * Main class of the module
 */
class Mobbex extends PaymentModule
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 'mobbex';

        $this->tab = 'payments_gateways';

        $this->version = MobbexHelper::MOBBEX_VERSION;

        $this->author = 'Mobbex Co';
        $this->controllers = array('redirect', 'notification', 'webhook');
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
        $this->_createStates();

        // Only if you want to publish your module on the Addons Marketplace
        $this->module_key = 'mobbex_checkout';
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
        Configuration::updateValue(MobbexHelper::K_EMBED, false);
        // Theme
        Configuration::updateValue(MobbexHelper::K_THEME, MobbexHelper::K_DEF_THEME);
        Configuration::updateValue(MobbexHelper::K_THEME_BACKGROUND, MobbexHelper::K_DEF_BACKGROUND);
        Configuration::updateValue(MobbexHelper::K_THEME_PRIMARY, MobbexHelper::K_DEF_PRIMARY);
        // Plans Widget
        Configuration::updateValue(MobbexHelper::K_PLANS, false);
        Configuration::updateValue(MobbexHelper::K_PLANS_TEXT, MobbexHelper::K_DEF_PLANS_TEXT);
        Configuration::updateValue(MobbexHelper::K_PLANS_BACKGROUND, MobbexHelper::K_DEF_PLANS_BACKGROUND);
        // DNI Fields
        Configuration::updateValue(MobbexHelper::K_OWN_DNI, false);
        Configuration::updateValue(MobbexHelper::K_CUSTOM_DNI, '');

        $this->_createTable();

        if (MobbexHelper::getPsVersion() === MobbexHelper::PS_16) {
            if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook('paymentReturn') || !$this->registerHook('displayProductButtons') || !$this->registerHook('displayCustomerAccountForm') || !$this->registerHook('actionCustomerAccountAdd')) {
                return false;
            }
        } else {
            if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn') || !$this->registerHook('displayProductAdditionalInfo') || !$this->registerHook('additionalCustomerFormFields') || !$this->registerHook('actionObjectCustomerUpdateAfter') || !$this->registerHook('actionObjectCustomerAddAfter')) {
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
        $form_values = $this->getConfigFormValues();
        foreach (array_keys($form_values) as $key) {
            Configuration::deleteByName($key);
        }

        $pendingOrderState = new OrderState((int) Configuration::get(MobbexHelper::K_OS_PENDING));
        $pendingOrderState->delete();

        $waitingOrderState = new OrderState((int) Configuration::get(MobbexHelper::K_OS_WAITING));
        $waitingOrderState->delete();

        $rejectedOrderState = new OrderState((int) Configuration::get(MobbexHelper::K_OS_REJECTED));
        $rejectedOrderState->delete();

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
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Access Token'),
                        'name' => MobbexHelper::K_ACCESS_TOKEN,
                        'required' => true,
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
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Theme Mode'),
                        'name' => MobbexHelper::K_THEME,
                        'is_bool' => false,
                        'required' => false,
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
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->l('Primary Color'),
                        'name' => MobbexHelper::K_THEME_PRIMARY,
                        'data-hex' => false,
                        'class' => 'mColorPicker',
                        'desc' => $this->l('Checkout Primary Color'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Logo Personalizado ( URL )'),
                        'name' => MobbexHelper::K_THEME_LOGO,
                        'required' => false,
                        'desc' => "Opcional. Debe utilizar la URL completa y debe ser HTTPS. Sólo configure su logo si es necesario que no se utilice el logo de su cuenta en Mobbex. Dimensiones: 250x250 píxeles. El Logo debe ser cuadrado para optimización.",
                    ),
                    // Embed SDK
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Experiencia de Pago en el Sitio'),
                        'name' => MobbexHelper::K_EMBED,
                        'is_bool' => true,
                        'required' => true,
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
                    // Reseller ID
                    array(
                        'type' => 'text',
                        'label' => $this->l('ID o Clave de Revendedor'),
                        'name' => MobbexHelper::K_RESELLER_ID,
                        'required' => false,
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
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Tax ID'),
                        'name' => MobbexHelper::K_PLANS_TAX_ID,
                        'required' => false,
                        'desc' => $this->l('Merchant Tax ID to Show configured plans'),
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->l('Text Color'),
                        'name' => MobbexHelper::K_PLANS_TEXT,
                        'data-hex' => false,
                        'class' => 'mColorPicker',
                        'desc' => $this->l('Plans Button Text Color'),
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->l('Background Color'),
                        'name' => MobbexHelper::K_PLANS_BACKGROUND,
                        'data-hex' => false,
                        'class' => 'mColorPicker',
                        'desc' => $this->l('Plans Button Background Color'),
                    ),
                    // DNI
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Agregar campo DNI'),
                        'name' => MobbexHelper::K_OWN_DNI,
                        'is_bool' => true,
                        'required' => true,
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
                    array(
                        'type' => 'text',
                        'label' => $this->l('Usar campo DNI existente'),
                        'name' => MobbexHelper::K_CUSTOM_DNI,
                        'required' => false,
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
            // Theme
            MobbexHelper::K_THEME => Configuration::get(MobbexHelper::K_THEME, MobbexHelper::K_DEF_THEME),
            MobbexHelper::K_THEME_BACKGROUND => Configuration::get(MobbexHelper::K_THEME_BACKGROUND, MobbexHelper::K_DEF_BACKGROUND),
            MobbexHelper::K_THEME_PRIMARY => Configuration::get(MobbexHelper::K_THEME_PRIMARY, MobbexHelper::K_DEF_PRIMARY),
            MobbexHelper::K_THEME_LOGO => Configuration::get(MobbexHelper::K_THEME_LOGO, ''),
            // Reseller ID
            MobbexHelper::K_RESELLER_ID => Configuration::get(MobbexHelper::K_RESELLER_ID, ''),
            // Plans Widget
            MobbexHelper::K_PLANS => Configuration::get(MobbexHelper::K_PLANS, false),
            MobbexHelper::K_PLANS_TAX_ID => Configuration::get(MobbexHelper::K_PLANS_TAX_ID, ''),
            MobbexHelper::K_PLANS_TEXT => Configuration::get(MobbexHelper::K_PLANS_TEXT, MobbexHelper::K_PLANS_TEXT),
            MobbexHelper::K_PLANS_BACKGROUND => Configuration::get(MobbexHelper::K_PLANS_BACKGROUND, MobbexHelper::K_PLANS_BACKGROUND),
            // DNI Fields
            MobbexHelper::K_OWN_DNI => Configuration::get(MobbexHelper::K_OWN_DNI, false),
            MobbexHelper::K_CUSTOM_DNI => Configuration::get(MobbexHelper::K_CUSTOM_DNI, ''),
            // Status
            MobbexHelper::K_OS_REJECTED => Configuration::get(MobbexHelper::K_OS_REJECTED, ''),
            MobbexHelper::K_OS_WAITING => Configuration::get(MobbexHelper::K_OS_WAITING, ''),
            MobbexHelper::K_OS_PENDING => Configuration::get(MobbexHelper::K_OS_PENDING, ''),
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
    }

    private function _createStates()
    {
        // Pending Status
        if (!Configuration::get(MobbexHelper::K_OS_PENDING)) {
            $order_state = new OrderState();
            $order_state->name = array();

            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Pending';
            }

            $order_state->send_email = false;
            $order_state->color = '#FEFF64';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;

            $order_state->module_name = $this->name;

            if ($order_state->add()) {
                // Add some image
            }

            Configuration::updateValue(MobbexHelper::K_OS_PENDING, (int) $order_state->id);
        }

        // Waiting Status
        if (!Configuration::get(MobbexHelper::K_OS_WAITING)) {
            $order_state = new OrderState();
            $order_state->name = array();

            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Waiting';
            }

            $order_state->send_email = false;
            $order_state->color = '#FEFF64';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;

            $order_state->module_name = $this->name;

            if ($order_state->add()) {
                // Add some image
            }

            Configuration::updateValue(MobbexHelper::K_OS_WAITING, (int) $order_state->id);
        }

        // Rejected Status
        if (!Configuration::get(MobbexHelper::K_OS_REJECTED)) {
            $order_state = new OrderState();
            $order_state->name = array();

            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Rejected Payment';
            }

            $order_state->send_email = false;
            $order_state->color = '#8F0621';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;

            $order_state->module_name = $this->name;

            if ($order_state->add()) {
                // Add some image
            }

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
            $this->smarty->assign('total', $trx['total']);
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
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $modal_active = Configuration::get(MobbexHelper::K_EMBED, false);

        if ($modal_active) {
            $payment_options = [$this->getIframePaymentOption()];
        } else {
            $payment_options = [$this->getExternalPaymentOption()];
        }

        return $payment_options;
    }

    public function getExternalPaymentOption()
    {
        $externalOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $externalOption->setCallToActionText($this->l('Pagar utilizando tarjetas, efectivo u otros'))
            ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:mobbex/views/templates/front/ps17.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo_transparent.png'));

        return $externalOption;
    }

    public function getIframePaymentOption()
    {
        $payment_data = MobbexHelper::getPaymentData();

        $this->context->smarty->assign(
            [
                'checkout_id' => $payment_data['id'],
                'checkout_url' => $payment_data['return_url'],
                'ps_version' => MobbexHelper::getPsVersion(),
            ]
        );

        $iframeOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $iframeOption->setCallToActionText($this->l('Pagar utilizando tarjetas, efectivo u otros'))
            ->setForm($this->context->smarty->fetch('module:mobbex/views/templates/front/modal_payment.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo_transparent.png'));

        return $iframeOption;
    }

    public function hookDisplayProductAdditionalInfo($params)
    {
        if (!Configuration::get(MobbexHelper::K_PLANS)) {
            return;
        }
        $style_settings = array(

            'text_color' => Configuration::get(MobbexHelper::K_PLANS_TEXT, '#ffffff'),
            'background' => Configuration::get(MobbexHelper::K_PLANS_BACKGROUND, '#8900ff'),

        );

        $this->context->smarty->assign(
            [
                'tax_id' => Configuration::get(MobbexHelper::K_PLANS_TAX_ID, ''),
                'price_amount' => Product::getPriceStatic(Tools::getValue('id_product'), true, null, 6),
                'style_settings' => $style_settings,
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
        if (Configuration::get(MobbexHelper::K_EMBED, false)) {
            $template = 'views/templates/front/modal_payment.tpl';

            $payment_data = MobbexHelper::getPaymentData();

            $this->context->smarty->assign(
                [
                    'checkout_id' => $payment_data['id'],
                    'checkout_url' => $payment_data['return_url'],
                    'payment_label' => $this->l('Pay with Credit/Debit Cards'),
                    'ps_version' => MobbexHelper::getPsVersion(),
                ]
            );

            return $this->display(__FILE__, $template);
        }

        $template = 'views/templates/hooks/payment.tpl';

        $this->context->smarty->assign(
            array(
                'payment_label' => $this->l('Pay with Credit/Debit Cards'),
            )
        );

        return $this->display(__FILE__, $template);
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
}
