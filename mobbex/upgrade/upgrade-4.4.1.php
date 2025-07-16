<?php

defined('_PS_VERSION_') || exit;

use Mobbex\PS\Checkout\Models\Config;

/**
 * Upgrade module db data.
 * 
 * IMPORTANT: Update the name of the function (and file) on every new release.
 * 
 * @param \Mobbex $module Mobbex module instance.
 * 
 * @return bool Upgrade result.
 */
function upgrade_module_4_4_1($module) {
    try {
        \Mobbex\PS\Checkout\Models\Logger::log('debug', 'Starting upgrade process');

        if (!$module->installer->createTables())
            throw new \Exception('Create tables failed');

        if (!$module->installer->createStates(Config::$orderStatuses))
            throw new \Exception('Create states failed');

        if (!$module->installer->createCostProduct())
            throw new \Exception('Create cost product failed');

        if (!$module->registrar->unregisterHooks($module))
            throw new \Exception('Unregister hooks failed');

        if (!$module->registrar->registerHooks($module))
            throw new \Exception('Register hooks failed');

        if (!$module->registrar->addExtensionHooks())
            throw new \Exception('Add extension hooks failed');

        return true;
    } catch (\Exception $e) {
        \Mobbex\PS\Checkout\Models\Logger::log('error', 'Upgrade ' . $e->getMessage());
        method_exists($module, 'addError') ? $module->addError($e->getMessage()) : false;

        return false;
    }
}