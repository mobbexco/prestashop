<?php

/**
 * mobbex.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 3.3.1
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
    /** @var \Mobbex\PS\Checkout\Models\Updater */
    public $updater;

    /** @var \Mobbex\PS\Checkout\Models\Config */
    public $config;

    /** @var \Mobbex\PS\Checkout\Models\Logger */
    public $logger;

    /** @var \Mobbex\PS\Checkout\Models\Registrar */
    public $registrar;

    /** @var \Mobbex\PS\Checkout\Observers\Observer */
    public $observer;

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

        //Mobbex Classes 
        $this->config    = new \Mobbex\PS\Checkout\Models\Config();
        $this->logger    = new \Mobbex\PS\Checkout\Models\Logger();
        $this->registrar = new \Mobbex\PS\Checkout\Models\Registrar();
        $this->observer  = new \Mobbex\PS\Checkout\Observers\Observer();

        // On 1.7.5 ignores the creation and finishes on an Fatal Error
        // Create the States if not exists because are really important
        if ($this::isEnabled($this->name))
            $this->createStates();

        // Only if you want to publish your module on the Addons Marketplace
        $this->updater    = new \Mobbex\PS\Checkout\Models\Updater();
        $this->module_key = 'mobbex_checkout';

        // Execute pending tasks if cron is disabled
        if (!defined('mobbexTasksExecuted') && !$this->config->settings['cron_mode'] && !\Mobbex\PS\Checkout\Models\Helper::needUpgrade())
            define('mobbexTasksExecuted', true) && \Mobbex\PS\Checkout\Models\Task::executePendingTasks();
    }

    public function __call($method, $arguments)
    {
        if (is_callable([$this->observer, $method]))
            return $this->observer->$method($arguments[0]);

        return false;
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
        
        //install Tables
        $this->createTables();

        // Try to create finnacial cost product
        if (!\Mobbex\PS\Checkout\Models\Helper::getProductIdByReference('mobbex-cost'))
            $this->createHiddenProduct('mobbex-cost', 'Costo financiero');

        return parent::install() 
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

    public function createStates()
    {
        foreach ($this->config->orderStatuses as $key => $value) {
            if (
                !\Configuration::hasKey($value['name'])
                || empty(\Configuration::get($value['name']))
                || !\Validate::isLoadedObject(new \OrderState(\Configuration::get($value['name'])))
            ) {
                $order_state = new OrderState();
                $order_state->name = array();

                // The locale parameter does not work as it should, so it is impossible to get the translation for each language
                foreach (\Language::getLanguages() as $language)
                    $order_state->name[$language['id_lang']] = $this->l($value['label']);

                $order_state->send_email  = $value['send_email'];
                $order_state->color       = $value['color'];
                $order_state->module_name = $this->name;

                $order_state->hidden = $order_state->delivery = $order_state->logable = $order_state->invoice = false;

                // Add to database
                $order_state->add();
                \Configuration::updateValue($value['name'], (int) $order_state->id);
        }
        }
    }

    public function createTables()
    {
        // Get install query from sql file
        $db = \DB::getInstance();
        $db->execute("SHOW TABLES LIKE '" . _DB_PREFIX_ . "mobbex_transaction';");

        // If mobbex transaction table exists
        if ($db->numRows()) {
            // Check if table has already been modified
            if ($db->executeS("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "mobbex_transaction` WHERE FIELD = 'id' AND EXTRA LIKE '%auto_increment%';"))
                return true;

            // If it was modified but id has not auto_increment property, add to column
            if ($db->executeS("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "mobbex_transaction` WHERE FIELD = 'id';"))
                return $db->execute("ALTER TABLE `" . _DB_PREFIX_ . "mobbex_transaction` MODIFY `id` INT NOT NULL AUTO_INCREMENT;");

            $sql = str_replace(['DB_PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], file_get_contents(dirname(__FILE__) . '/sql/alter.sql'));
                return $db->execute($sql);
        }
        
        $sql = str_replace(['DB_PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], file_get_contents(dirname(__FILE__) . '/sql/create.sql'));
            return $db->execute($sql);
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
            Tools::redirectAdmin(\Mobbex\PS\Checkout\Models\Helper::getUpgradeURL());
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
            if (\Mobbex\PS\Checkout\Models\Helper::needUpgrade())
                $form['form']['warning'] = 'Actualice la base de datos desde <a href="' . \Mobbex\PS\Checkout\Models\Helper::getUpgradeURL() . '">aquí</a> para que el módulo funcione correctamente.';

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

    /**
     * Create a hidden product.
     * 
     * @param string $reference String to identify and get product after.
     * @param string $name The name of product.
     * 
     * @return bool Save result.
     */
    public function createHiddenProduct($reference, $name)
    {
        $product = new \Product(null, false, \Configuration::get('PS_LANG_DEFAULT'));
        $product->hydrate([
            'reference'           => $reference,
            'name'                => $name,
            'quantity'            => 9999999,
            'is_virtual'          => false,
            'indexed'             => 0,
            'visibility'          => 'none',
            'id_category_default' => \Configuration::get('PS_HOME_CATEGORY'),
            'link_rewrite'        => $reference,
        ]);

        // Save to db and return
        return $product->save()
            && $product->addToCategories(\Configuration::get('PS_HOME_CATEGORY'))
            && \StockAvailable::setQuantity($product->id, null, 9999999);
    }
}