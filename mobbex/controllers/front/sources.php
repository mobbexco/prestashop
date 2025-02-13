<?php

defined('_PS_VERSION_') || exit;

use Mobbex\PS\Checkout\Models\Config;
use Mobbex\PS\Checkout\Models\Logger;

class MobbexSourcesModuleFrontController extends ModuleFrontController
{   
    public function postProcess()
    {
        // We don't do anything if the module has been disabled by the merchant
        if ($this->module->active == false)
            Logger::log(
                'fatal',
                'sources > postProcess | Sources update on module inactive.',
                $_REQUEST
            );

        if (!Config::validateHash(Tools::getValue('hash')))
            return;

            switch (Tools::getValue('action')) {
                case 'update':
                    self::updateMobbexSources();
                    break;
                case 'load':
                    $this->loadSources();
                    break;
            }
    }

    /**
     * Load sources from API
     * 
     */
    public function loadSources()
    {
        $response = [
            'success' => false,
            'message' => 'Error obtaining mobbex sources.',
            'data' => []
        ];

        //Try to get installments from API
        try {
            // Get request data
            $postData = isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' ? json_decode(file_get_contents('php://input'), true) : $_POST;
            $price    = isset($postData['price']) ? $postData['price'] : null;
            $ids      = isset($postData['ids']) ? json_decode($postData['ids']) : null;

            if(!$ids || $price === null || !is_array($ids))
                throw new \Exception("Error missing data in sources request.", 1);

            extract($this->config->getProductsPlans($ids));
            $response['data']    = \Mobbex\Repository::getSources($price, \Mobbex\Repository::getInstallments($ids, $common_plans, $advanced_plans));
            $response['success'] = true;
            $response['message'] = 'Sources loaded successfully.';

        } catch (\Exception $e) {
            Logger::log('error', 'mobbex > displayPlansWidget | Error Obtaining Mobbex installments from API', $e->getMessage());
        }
        
        // Return response
        header('Content-Type: application/json');
        die(json_encode($response));
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
                \Mobbex\PS\Checkout\Models\CustomFields::saveCustomField($shopId, 'shop', $key, json_encode(${$value}));
        } catch (\Exception $e) {
            Logger::log('error', 'config > updateMobbexSources | Error Obtaining Mobbex sources from API', $e->getMessage());
        }

        return compact('names', 'common', 'advanced', 'groups');
    }
}