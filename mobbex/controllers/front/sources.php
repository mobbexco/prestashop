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

        $action = Tools::getValue('action');

        if ($action == 'update')
            Config::updateMobbexSources();
        else if ($action == 'getSources')
            $this->getSources();
    }

    /**
     * Action that gets sources from Mobbex API
     * 
     * @return void
     */
    public function getSources() {
        // Gets values from query params
        $total    = (float) Tools::getValue('total');
        $products = explode(',', Tools::getValue('mbbxProducts') ?: '');

        // Filter out non-numeric values
        $products = array_filter($products, function ($product) {
            return is_numeric($product);
        });

        // Extract products plans
        extract(Config::getProductsPlans($products));

        // Gets installments
        $installments = \Mobbex\Repository::getInstallments(
            $products,
            $common_plans,
            $advanced_plans
        );

        try {
            // Get sources from Moobbex API
            $sources = \Mobbex\Repository::getSources(
                $total,
                $installments
            );

            // Return json with sources and total
            die(json_encode([
                'success'      => true,
                'productTotal' => $total,
                'sources'      => $sources,
            ]));

        } catch (\Exception $e) {
            Logger::log('error', 'Sources > getSources', $e->getMessage());
            die (json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }
    }
}