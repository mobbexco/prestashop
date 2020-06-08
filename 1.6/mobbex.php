<?php
/**
 * mobbex.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 1.0.0
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
        // Theme
        Configuration::updateValue(MobbexHelper::K_THEME, MobbexHelper::K_DEF_THEME);
        Configuration::updateValue(MobbexHelper::K_THEME_BACKGROUND, MobbexHelper::K_DEF_BACKGROUND);
        Configuration::updateValue(MobbexHelper::K_THEME_PRIMARY, MobbexHelper::K_DEF_PRIMARY);

        $this->_createTable();

        if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook('paymentReturn')) {
            return false;
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
                                'value' => true,
                                'label' => $this->l('Light Mode'),
                            ],
                            [
                                'id' => 'm_theme_dark',
                                'value' => false,
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
                    // Reseller ID
                    array(
                        'type' => 'text',
                        'label' => $this->l('ID o Clave de Revendedor'),
                        'name' => MobbexHelper::K_RESELLER_ID,
                        'required' => false,
                        'desc' => "Ingrese este identificador sólo si se es parte de un programa de reventas. El identificador NO debe tener espacios, solo letras, números o guiones. El identificador se agregará a la referencia de Pago para identificar su venta.",
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
            // Theme
            MobbexHelper::K_THEME => Configuration::get(MobbexHelper::K_THEME, MobbexHelper::K_DEF_THEME),
            MobbexHelper::K_THEME_BACKGROUND => Configuration::get(MobbexHelper::K_THEME_BACKGROUND, MobbexHelper::K_DEF_BACKGROUND),
            MobbexHelper::K_THEME_PRIMARY => Configuration::get(MobbexHelper::K_THEME_PRIMARY, MobbexHelper::K_DEF_PRIMARY),
            MobbexHelper::K_THEME_LOGO => Configuration::get(MobbexHelper::K_THEME_LOGO, ''),
            // Reseller ID
            MobbexHelper::K_RESELLER_ID => Configuration::get(MobbexHelper::K_RESELLER_ID, ''),
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
            $this->context->smarty->assign('status', $order->getCurrentStateFull($this->context->language->id)['name']);
            $this->context->smarty->assign('total', $order->getTotalPaid());
            $this->context->smarty->assign('payment', $order->payment);
            $this->context->smarty->assign('mobbex_data', $trx);
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

    /**
     * Logic to execute when the hook 'displayPayment' is fired
     *
     * Support for 1.6 Only
     *
     * @return string
     */
    public function hookPayment()
    {
        $template = 'views/templates/hooks/payment.tpl';

        $this->context->smarty->assign(
            array(
                'payment_label' => $this->l('Pay with Credit/Debit Cards'),
            )
        );

        return $this->display(__FILE__, $template);
    }
}
