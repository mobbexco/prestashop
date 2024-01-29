<?php

namespace Mobbex\PS\Checkout\Models;

if (!defined('_PS_VERSION_'))
    exit;

class Config
{
    const MODULE_VERSION = '4.2.0';
    const EMBED_VERSION  = '1.0.23';

    const PS16           = '1.6';
    const PS17           = '1.7';

    public $settings     = [];
    public $default      = [];

    //Add Mobbex Order Statuses
    public $orderStatuses = [
        'mobbex_status_approved'   => ['name' => 'MOBBEX_OS_APPROVED', 'label' => 'Transaction in Process', 'color' => '#5bff67', 'send_email' => true],
        'mobbex_status_pending'    => ['name' => 'MOBBEX_OS_PENDING', 'label'  => 'Pending', 'color' => '#FEFF64', 'send_email' => false],
        'mobbex_status_waiting'    => ['name' => 'MOBBEX_OS_WAITING', 'label'  => 'Waiting', 'color' => '#FEFF64', 'send_email' => false],
        'mobbex_status_rejected'   => ['name' => 'MOBBEX_OS_REJECTED','label'  => 'Rejected Payment', 'color' => '#8F0621', 'send_email' => false],
        'mobbex_status_authorized' => ['name' => 'MOBBEX_OS_AUTHORIZED','label'  => 'Authorized', 'color' => '#FEFF64', 'send_email' => false],
        'mobbex_status_expired'    => ['name' => 'MOBBEX_OS_EXPIRED','label' => 'Checkout Expirado', 'color' => '#999999', 'send_email' => false],
    ];

    public function __construct()
    {
        $this->settings = $this->getSettings();
    }

    /** MODULE SETTINGS **/

    /**
     * Returns an array of config options for prestashop module config.
     * @param bool $extensionOptions 
     */
    public function getConfigForm($extensionOptions = true)
    {
        $form = require __DIR__ . '/../utils/config-form.php';
        return $extensionOptions ? \Mobbex\PS\Checkout\Models\Registrar::executeHook('displayMobbexConfiguration', true, $form) : $form;
    }

    /**
     * Get the Mobbex module settigns from config form array.
     *
     * @param string $key specifies the key used in the array that method returns
     * @param bool $extensionOptions allow to extend options with a hook
     * @return array $settings 
     */
    public function getSettings($key = 'key', $extensionOptions = false)
    {
        $settings = [];

        foreach ($this->getConfigForm($extensionOptions)['form']['input'] as $input)
            $settings[$input[$key]]  = \Configuration::getIdByName($input['name']) ? \Configuration::get($input['name']) : $input['default'];

        return $settings;
    }

    /**
     * Delete all the mobbex settings from the prestashop database.
     */
    public function deleteSettings()
    {
        foreach ($this->getConfigForm(false)['form']['input'] as $setting)
            \Configuration::deleteByName($setting['name']);
    }

    /**
     * Get the table & column name where dni field is stored from configuration.
     * @return array
     */
    public function getCustomDniColumn()
    {
        //Default values
        $data = [
            'table'      => _DB_PREFIX_ . 'customer',
            'identifier' => 'customer_id',
        ];

        if ($this->settings['custom_dni'] != '') {
            foreach (explode(':', $this->settings['custom_dni']) as $key => $value) {
                if ($key === 0 && count(explode(':', $this->settings['custom_dni'])) > 1) {
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
    public function getCatalogSetting($id, $fieldName, $catalogType = 'product')
    {
        if (strpos($fieldName, '_plans'))
            return json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($id, $catalogType, $fieldName)) ?: [];

        return \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($id, $catalogType, $fieldName) ?: '';
    }

    /**
     * Get active plans for a given products.
     * 
     * @param array $products Products list
     * 
     * @return array $array
     */
    public function getProductsPlans($products)
    {
        $common_plans = $advanced_plans = [];

        foreach ($products as $product) {
            $id = isset($product['id_product']) ? $product['id_product'] : $product;
            $product_plans = $this->getCatalogPlans($id);
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
    public function getCatalogPlans($id, $catalog_type = 'product', $admin = false)
    {
        //Get product plans
        $common_plans   = $this->getCatalogSetting($id, 'common_plans', $catalog_type) ?: [];
        $advanced_plans = $this->getCatalogSetting($id, 'advanced_plans', $catalog_type) ?: [];
        $product        = new \Product($id, false, (int) \Configuration::get('PS_LANG_DEFAULT'));

        //Get plans from categories
        if (!$admin && $catalog_type === 'product') {
            foreach ($product->getCategories() as $categoryId) {
                $common_plans   = array_merge($common_plans, $this->getCatalogSetting($categoryId, 'common_plans', 'category'));
                $advanced_plans = array_merge($advanced_plans, $this->getCatalogSetting($categoryId, 'advanced_plans', 'category'));
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
    public function getEntityFromProduct($product)
    {
        $entity = \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($product->id, 'product', 'entity');

        if ($entity)
            return $entity;

        // Try to get from their categories
        foreach ($product->getCategories() as $categoryId) {
            if (\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($categoryId, 'category', 'entity'))
                return \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($categoryId, 'category', 'entity');
        }

        return false;
    }

    /** SOURCES SETTINGS **/

    public function getStoredSources()
    {
        $shopId = \Context::getContext()->shop->id ?: null;

        // Try to get sources from db
        $sources = [
            'names'    => json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($shopId, 'shop', 'source_names'), true)     ?: [],
            'common'   => json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($shopId, 'shop', 'common_sources'), true)   ?: [],
            'advanced' => json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($shopId, 'shop', 'advanced_sources'), true) ?: [],
            'groups'   => json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($shopId, 'shop', 'source_groups'), true) ?: [],
        ];

        if (!$sources['common'] || !$sources['advanced'])
            $sources = $this->updateMobbexSources();

        return $sources;
    }

    /**
     * Save sources in config data
     * 
     */
    public function updateMobbexSources()
    {
        $source_names = $common_sources = $advanced_sources = $source_groups = [];

        try {
            foreach (\Mobbex\Repository::getSources() as $source) {
                if (empty($source['installments']['list']))
                    continue;

                // Format field data
                foreach ($source['installments']['list'] as $plan) {
                    $common_sources[$plan['reference']] = [
                        'id'          => "common_plan_$plan[reference]",
                        'key'         => isset($plan['reference']) ? $plan['reference'] : '',
                        'label'       => isset($plan['name']) ? $plan['name'] : '',
                        'description' => isset($plan['description']) ? $plan['description'] : '',
                    ];

                    $source_groups[$plan['name']][] = $source['source']['reference'];
                    $source_groups[$plan['name']]   = array_unique($source_groups[$plan['name']]);
                }
            }

            foreach (\Mobbex\Repository::getSourcesAdvanced() as $source) {
                if (empty($source['installments']))
                    continue;

                // Save source name
                $source_names[$source['source']['reference']] = $source['source']['name'];

                // Format field data
                foreach ($source['installments'] as $plan) {
                    $advanced_sources[$source['source']['reference']][] = [
                        'id'          => "advanced_plan_$plan[uid]",
                        'key'         => isset($plan['uid']) ? $plan['uid'] : '',
                        'label'       => isset($plan['name']) ? $plan['name'] : '',
                        'description' => isset($plan['description']) ? $plan['description'] : '',
                    ];
                }
            }

            // Save to db
            $shopId = \Context::getContext()->shop->id ?: null;
            foreach (['source_names', 'common_sources', 'advanced_sources', 'source_groups'] as $value)
                \Mobbex\PS\Checkout\Models\CustomFields::saveCustomField($shopId, 'shop', $value, json_encode(${$value}));
        } catch (\Exception $e) {
            $this->logger->log('error', 'config > updateMobbexSources | Error Obtaining Mobbex sources from API', $e->getMessage());
        }

        return compact('names', 'common', 'advanced');
    }

    /** UTILS **/

    /**
     * Used to translate a given label.
     * @param string $string
     * @param string $source
     */
    public function l($string, $source = 'mobbex')
    {
        return \Translate::getModuleTranslation(
            'mobbex',
            $string,
            $source
        );
    }
}