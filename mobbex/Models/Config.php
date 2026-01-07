<?php

namespace Mobbex\PS\Checkout\Models;

if (!defined('_PS_VERSION_'))
    exit;

class Config
{
    const MODULE_VERSION = '4.5.1';
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
            $sources = self::updateMobbexSources();

        return $sources;
    }

    /**
     * Save sources in config data
     * 
     */
    public static function updateMobbexSources()
    {
        $names = $common = $advanced = $groups = [];

        try {
            foreach (\Mobbex\Repository::getSources() as $source) {
                if (empty($source['installments']['list']))
                    continue;

                // Format field data
                foreach ($source['installments']['list'] as $plan) {
                    $common[$plan['reference']] = [
                        'id'          => "common_plan_$plan[reference]",
                        'key'         => isset($plan['reference']) ? $plan['reference'] : '',
                        'label'       => isset($plan['name']) ? $plan['name'] : '',
                        'description' => isset($plan['description']) ? $plan['description'] : '',
                    ];

                    $groups[$plan['name']][] = $source['source']['reference'];
                    $groups[$plan['name']]   = array_unique($groups[$plan['name']]);
                }
            }

            foreach (\Mobbex\Repository::getSourcesAdvanced() as $source) {
                if (empty($source['installments']))
                    continue;

                // Save source name
                $names[$source['source']['reference']] = $source['source']['name'];

                // Format field data
                foreach ($source['installments'] as $plan) {
                    $advanced[$source['source']['reference']][] = [
                        'id'          => "advanced_plan_$plan[uid]",
                        'key'         => isset($plan['uid']) ? $plan['uid'] : '',
                        'label'       => isset($plan['name']) ? $plan['name'] : '',
                        'description' => isset($plan['description']) ? $plan['description'] : '',
                    ];
                }
            }

            // Save to db
            $shopId = \Context::getContext()->shop->id ?: null;
            foreach (['source_names' => 'names', 'common_sources' => 'common', 'advanced_sources' => 'advanced', 'source_groups' => 'groups'] as $key => $value)
                CustomFields::saveCustomField($shopId, 'shop', $key, json_encode(${$value}));
        } catch (\Exception $e) {
            Logger::log('error', 'config > updateMobbexSources | Error Obtaining Mobbex sources from API', $e->getMessage());
        }

        return compact('names', 'common', 'advanced', 'groups');
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
            self::$settings['api_key'] . '!' . self::$settings['access_token']
        );
    }

    /* Finance Widget */
    
    /**
     * Handles featured plans configuration and return the correct value
     * 
     * @param array      $products_id
     * @param boolean    $cartPage
     * 
     * @return string|array|null
     */
    public static function handleFeaturedPlans($products_id, $cartPage)
    {
        if ($cartPage)
            return self::$settings['show_featured_installments_on_cart']
                ? []
                : null;
        
        if (!is_array($products_id) || !$products_id)
            return null;

        $id = reset($products_id);

        $product = new \Product($id, false, (int) \Configuration::get('PS_LANG_DEFAULT'));

        $showFeatured = self::getAllPlansConfiguratorSettings($id, $product, "show_featured");
        if (!$showFeatured)
            return null;

        $manualConfig = self::getAllPlansConfiguratorSettings($id, $product, "manual_config");
        if (!$manualConfig)
            return [];

        return self::getAllPlansConfiguratorSettings($id, $product, "featured_plans");
    }

    /**
     * Get specific field values from a product and their categories.
     * 
     * @param int|string $id
     * @param object $product
     * @param string $fieldName
     * 
     * @return string|bool
     */
    public static function getAllPlansConfiguratorSettings($id, $product, $fieldName) 
    {
        // gets product settings
        $productFieldValue = self::getCatalogSetting($id, $fieldName, 'product');

        // gets categories settings
        // merge in array value case
        if (is_array($productFieldValue)) {
            $productFieldValue = self::shouldDisplayFeaturedPlans($id) 
                ? $productFieldValue 
                : [];

            foreach ($product->getCategories()  as $categoryId) {
                $categoryFieldValue = self::shouldDisplayFeaturedPlans($categoryId, 'category') 
                    ? self::getCatalogSetting($categoryId, $fieldName, 'category')
                    : [];

                $productFieldValue  = array_merge(
                    $productFieldValue, 
                    $categoryFieldValue
                );
            }

            return $productFieldValue;
        }

        // check flags in string value case
        $categoriesFieldValues = [];
        foreach ($product->getCategories() as $categoryId)
            array_push(
                $categoriesFieldValues,
                self::getCatalogSetting($categoryId, $fieldName, 'category')
            );

        return empty($categoriesFieldValues) 
            ? $productFieldValue == "yes"
            : (in_array("yes", $categoriesFieldValues) || $productFieldValue == "yes");
    }
    
    /**
     * Get product display featured plans settings
     * 
     * @param string|int $id
     * @param string $catalogType
     * 
     * @return bool
     */
    private static function shouldDisplayFeaturedPlans($id, $catalogType = "product") 
    {
        return (
            (self::getCatalogSetting($id, "show_featured", $catalogType) == "yes")
            && (self::getCatalogSetting($id, "manual_config", $catalogType) == "yes")
        );
    }
}
