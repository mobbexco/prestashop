<?php

namespace Mobbex\PS\Checkout\Models;

if (!defined('_PS_VERSION_'))
    exit;

class Registrar
{
    public $hooks = [
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

    public $ps16Hooks = [
        'payment',
        'header',
        'displayMobileHeader',
        'displayProductButtons',
        'displayCustomerAccountForm',
        'actionCustomerAccountAdd',
        'displayShoppingCartFooter',
    ];

    public $ps17Hooks = [
        'paymentOptions',
        'displayHeader',
        'additionalCustomerFormFields',
        'actionObjectCustomerUpdateAfter',
        'actionObjectCustomerAddAfter',
        'displayProductPriceBlock',
        'displayExpressCheckout',
        'ActionAfterUpdateCategoryFormHandler',
    ];

    /**
     * Register module hooks dependig on prestashop version.
     * 
     * @return bool Result of the registration
     */
    public function registerHooks($module)
    {
        // Merge current version hooks with common hooks
        $this->hooks = array_merge($this->hooks, _PS_VERSION_ > '1.7' ? $this->ps17Hooks : $this->ps16Hooks);

        foreach ($this->hooks as $hookName) {
            if (!$module->registerHook($hookName))
                return false;
        }

        return true;
    }

    /**
     * Unregister all current module hooks.
     * 
     * @return bool Result.
     */
    public function unregisterHooks($module)
    {
        // Get hooks used by module
        $hooks = \Db::getInstance()->executeS(
            'SELECT DISTINCT(`id_hook`) FROM `' . _DB_PREFIX_ . 'hook_module` WHERE `id_module` = ' . $module->id
        ) ?: [];

        foreach ($hooks as $hook) {
            if (!$module->unregisterHook($hook['id_hook']) || !$module->unregisterExceptions($hook['id_hook']))
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
            if (!\Hook::getIdByName($name)) {
                $hook              = new \Hook();
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
     * Execute a hook and retrieve the response.
     * 
     * @param string $name The hook name.
     * @param bool $filter Filter first arg in each execution.
     * @param mixed ...$args Arguments to pass.
     * 
     * @return mixed Last execution response or value filtered. Null on exceptions.
     */
    public static function executeHook($name, $filter = false, ...$args)
    {
        try {
            // Get modules registerd and first arg to return as default
            $modules = \Hook::getHookModuleExecList($name) ?: [];
            $value   = $filter ? reset($args) : false;

            foreach ($modules as $moduleData) {
                $module = \Module::getInstanceByName($moduleData['module']);
                $method = [$module, 'hook' . ucfirst($name)];

                // Only execute if is callable
                if (!is_callable($method))
                    continue;

                $value = call_user_func_array($method, $args);

                if ($filter)
                    $args[0] = $value;
            }

            return $value;
        } catch (\Exception $e) {
            $logger = new \Mobbex\PS\Checkout\Models\Logger();
            $logger->log('error', 'Registrar > executeHook | Hook Error', $e->getMessage());
        }
    }
}
