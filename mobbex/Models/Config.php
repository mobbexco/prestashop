<?php

namespace Mobbex\PS\Checkout\Models;

if (!defined('_PS_VERSION_'))
    exit;

class Config
{
    const MODULE_VERSION = '3.3.1';
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
        return $extensionOptions ? Registrar::executeHook('displayMobbexConfiguration', true, $form) : $form;
    }

    /**
     * Get the Mobbex module settigns from config form array.
     *
     * @param string $key specifies the key used in the array that method returns
     * @return array $settings 
     */
    public function getSettings($key = 'key')
    {
        $settings = [];

        foreach ($this->getConfigForm()['form']['input'] as $input)
            $settings[$input[$key]]  = \Configuration::getIdByName($input['name']) ? \Configuration::get($input['name']) : $input['default'];

        return $settings;
    }

    /**
     * Delete all the mobbex settings from the prestashop database.
     */
    public function deleteSettings()
    {
        foreach ($this->getConfigForm()['form']['input'] as $setting)
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
            foreach (explode(':', $this->config->settings['custom_dni']) as $key => $value) {
                if ($key === 0 && count(explode(':', $this->config->settings['custom_dni'])) > 1) {
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
            return json_decode(CustomFields::getCustomField($id, $catalogType, $fieldName)) ?: [];

        return CustomFields::getCustomField($id, $catalogType, $fieldName) ?: '';
    }

    /**
     * Get active plans for a given products.
     * @param array $products
     * @return array $array
     */
    public function getProductPlans($products)
    {
        $common_plans = $advanced_plans = [];

        foreach ($products as $product) {
            $product = $product instanceof \Product ? $product : new \Product($product, false, (int) \Configuration::get('PS_LANG_DEFAULT'));
            foreach (['common_plans', 'advanced_plans'] as $value) {
                //Get product active plans
                ${$value} = array_merge($this->getCatalogSetting($product instanceof \Product ? $product->id : $product, $value), ${$value});
                //Get product category active plans
                foreach ($product->getCategories() as $categoryId)
                    ${$value} = array_unique(array_merge(${$value}, $this->getCatalogSetting($categoryId, $value, 'category')));
            }
        }

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

    public function getStoredSources()
    {
        $shopId = \Context::getContext()->shop->id ?: null;

        // Try to get sources from db
        $sources = [
            'names'    => json_decode(CustomFields::getCustomField($shopId, 'shop', 'source_names'), true)     ?: [],
            'common'   => json_decode(CustomFields::getCustomField($shopId, 'shop', 'common_sources'), true)   ?: [],
            'advanced' => json_decode(CustomFields::getCustomField($shopId, 'shop', 'advanced_sources'), true) ?: [],
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
        $source_names = $common_sources = $advanced_sources = [];

        foreach (\Mobbex\Repository::getSources() as $source) {
            if (empty($source['installments']['list']))
                continue;

            // Format field data
            foreach ($source['installments']['list'] as $plan) {
                $common_sources[$plan['reference']] = [
                    'id'    => "common_plan_$plan[reference]",
                    'key'   => $plan['reference'],
                    'label' => $plan['name'] ?: $plan['description'],
                ];
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
                    'id'    => "advanced_plan_$plan[uid]",
                    'key'   => $plan['uid'],
                    'label' => $plan['name'] ?: $plan['description'],
                ];
            }
        }

        // Save to db
        $shopId = \Context::getContext()->shop->id ?: null;
        foreach (['source_names', 'common_sources', 'advanced_sources'] as $value)
            CustomFields::saveCustomField($shopId, 'shop', $value, json_encode(${$value}));

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