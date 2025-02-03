<?php

namespace Mobbex\PS\Checkout\Models;

if (!defined('_PS_VERSION_'))
    exit;

class Config
{
    const MODULE_VERSION = '4.3.0';
    const EMBED_VERSION  = '1.0.23';
    const PS16           = '1.6';
    const PS17           = '1.7';

    public static $settings = [];
    public static $orderStatuses = [
        'mobbex_status_approved'   => ['name' => 'MOBBEX_OS_APPROVED', 'label' => 'Transaction in Process', 'color' => '#5bff67', 'send_email' => true],
        'mobbex_status_pending'    => ['name' => 'MOBBEX_OS_PENDING', 'label'  => 'Pending', 'color' => '#FEFF64', 'send_email' => false],
        'mobbex_status_waiting'    => ['name' => 'MOBBEX_OS_WAITING', 'label'  => 'Waiting', 'color' => '#FEFF64', 'send_email' => false],
        'mobbex_status_rejected'   => ['name' => 'MOBBEX_OS_REJECTED', 'label'  => 'Rejected Payment', 'color' => '#8F0621', 'send_email' => false],
        'mobbex_status_authorized' => ['name' => 'MOBBEX_OS_AUTHORIZED', 'label'  => 'Authorized', 'color' => '#FEFF64', 'send_email' => false],
        'mobbex_status_expired'    => ['name' => 'MOBBEX_OS_EXPIRED', 'label' => 'Checkout Expirado', 'color' => '#999999', 'send_email' => false],
    ];

    public static function init()
    {
        self::$settings = self::getSettings();

        return self::class;
    }

    /** MODULE SETTINGS **/

    /**
     * Returns an array of config options for prestashop module config.
     * @param bool $extensionOptions 
     */
    public static function getConfigForm($extensionOptions = true)
    {
        if ($extensionOptions)
            $extensionOptions = self::checkExtension();
        
        $form = require __DIR__ . '/../utils/config-form.php';
        return $extensionOptions ? Registrar::executeHook('displayMobbexConfiguration', true, $form) : $form;
    }

    /**
     * Get the Mobbex module settigns from config form array.
     *
     * @param string $key specifies the key used in the array that method returns
     * @return array $settings 
     */
    public static function getSettings($key = 'key')
    {
        $settings = [];

        // Get values saved on database
        $values = \Db::getInstance()->executes(
            "SELECT name, value FROM " . _DB_PREFIX_ . "configuration WHERE `name` LIKE 'MOBBEX_%';"
        );

       $names = array_column($values, 'name');

        foreach (self::getConfigForm()['form']['input'] as $input) {
            $position = array_search($input['name'], $names);
            $settings[$input[$key]] = $position !== false ? $values[$position]['value'] : $input['default'];
        }

        return $settings;
    }

    /**
     * Delete all the mobbex settings from the prestashop database.
     */
    public static function deleteSettings()
    {
        return \Db::getInstance()->executes(
            "DELETE FROM " . _DB_PREFIX_ . "configuration WHERE `name` LIKE 'MOBBEX_%' AND `name` NOT LIKE 'MOBBEX_OS_%';"
        );
    }

    /**
     * Get the table & column name where dni field is stored from configuration.
     * @return array
     */
    public static function getCustomDniColumn()
    {
        //Default values
        $data = [
            'table'      => _DB_PREFIX_ . 'customer',
            'identifier' => 'customer_id',
        ];

        if (self::$settings['custom_dni'] != '') {
            foreach (explode(':', self::$settings['custom_dni']) as $key => $value) {
                if ($key === 0 && count(explode(':', self::$settings['custom_dni'])) > 1) {
                    $data['table'] = trim($value);
                } else if ($key === 1) {
                    $data['identifier'] = trim($value);
                } else {
                    $data['dniColumn'] = trim($value);
                }
            }
        }

        return $data;
    }

    /** CATALOG SETTINGS **/

    /**
     * Retrieve the given product/category option.
     * 
     * @param int|string $id
     * @param string $object
     * @param string $catalogType
     * 
     * @return array|string
     */
    public static function getCatalogSetting($id, $fieldName, $catalogType = 'product')
    {
        if (strpos($fieldName, '_plans'))
            return json_decode(CustomFields::getCustomField($id, $catalogType, $fieldName)) ?: [];

        return CustomFields::getCustomField($id, $catalogType, $fieldName) ?: '';
    }

    /**
     * Get active plans for a given products.
     * 
     * @param array $products Products list
     * 
     * @return array $array
     */
    public static function getProductsPlans($products)
    {
        $common_plans = $advanced_plans = [];

        foreach ($products as $product) {
            $id = is_array($product) && isset($product['id_product']) ? $product['id_product'] : $product;
            $product_plans = self::getCatalogPlans($id);
            //Merge all catalog plans
            $common_plans   = array_merge($common_plans, $product_plans['common_plans']);
            $advanced_plans = array_merge($advanced_plans, $product_plans['advanced_plans']);
        }

        return compact('common_plans', 'advanced_plans');
    }

    /**
     * Get all the Mobbex plans from a given product or category id.
     * 
     * @param string $id Product/Cat id.
     * @param string $catalog_type
     * 
     * @return array
     */
    public static function getCatalogPlans($id, $catalog_type = 'product', $admin = false)
    {
        //Get product plans
        $common_plans   = self::getCatalogSetting($id, 'common_plans', $catalog_type) ?: [];
        $advanced_plans = self::getCatalogSetting($id, 'advanced_plans', $catalog_type) ?: [];
        $product        = new \Product($id, false, (int) \Configuration::get('PS_LANG_DEFAULT'));

        //Get plans from categories
        if (!$admin && $catalog_type === 'product') {
            foreach ($product->getCategories() as $categoryId) {
                $common_plans   = array_merge($common_plans, self::getCatalogSetting($categoryId, 'common_plans', 'category'));
                $advanced_plans = array_merge($advanced_plans, self::getCatalogSetting($categoryId, 'advanced_plans', 'category'));
            }
        }

        //Avoid duplicated plans
        $common_plans   = array_unique($common_plans);
        $advanced_plans = array_unique($advanced_plans);

        return compact('common_plans', 'advanced_plans');
    }

    /**
     * Retrieve entity configured by product or parent categories or bool if not configured.
     * 
     * @param \Product $product
     * 
     * @return string|bool
     */
    public static function getEntityFromProduct($product)
    {
        $entity = CustomFields::getCustomField($product->id, 'product', 'entity');

        if ($entity)
            return $entity;

        // Try to get from their categories
        foreach ($product->getCategories() as $categoryId) {
            if (CustomFields::getCustomField($categoryId, 'category', 'entity'))
                return CustomFields::getCustomField($categoryId, 'category', 'entity');
        }

        return false;
    }

    /** SOURCES SETTINGS **/

    public static function getStoredSources()
    {
        $shopId = \Context::getContext()->shop->id ?: null;

        // Try to get sources from db
        $sources = [
            'names'    => json_decode(CustomFields::getCustomField($shopId, 'shop', 'source_names'), true)     ?: [],
            'common'   => json_decode(CustomFields::getCustomField($shopId, 'shop', 'common_sources'), true)   ?: [],
            'advanced' => json_decode(CustomFields::getCustomField($shopId, 'shop', 'advanced_sources'), true) ?: [],
            'groups'   => json_decode(CustomFields::getCustomField($shopId, 'shop', 'source_groups'), true) ?: [],
        ];

        if (!$sources['common'] || !$sources['advanced'] || !$sources['groups'])
            $sources = \MobbexSourcesModuleFrontController::updateMobbexSources();

        return $sources;
    }

    /** UTILS **/

    /**
     * Used to translate a given label.
     * @param string $string
     * @param string $source
     */
    public static function l($string, $source = 'mobbex')
    {
        return \Translate::getModuleTranslation(
            'mobbex',
            $string,
            $source
        );
    }

    /**
     * Checks if there's an / any Mobbex extension enabled
     * @param string $extension extension name (in lowercase)
     * 
     * @return bool
     */
    public static function checkExtension($extension = '')
    {
        return empty($extension) ? \Module::isEnabled('mobbex_marketplace') || \Module::isEnabled('mobbex_subscriptions') : \Module::isEnabled($extension);
    }

    public static function validateHash($hash)
    {
        return $hash == md5(
            self::settings['api_key'] . '!' . self::settings['access_token']
        );
    }
}
