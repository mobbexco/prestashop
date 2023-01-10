<?php

defined('_PS_VERSION_') || exit;

/**
 * Upgrade module db data.
 * 
 * IMPORTANT: Update the name of the function (and file) on every new release.
 * 
 * @param \Mobbex $module Mobbex module instance.
 * 
 * @return bool Upgrade result.
 */
function upgrade_module_3_5_0($module) {
    return $module->installer->createTables()
        && $module->installer->createStates($module->config->orderStatuses)
        && $module->installer->createCostProduct()
        && $module->registrar->unregisterHooks($module)
        && $module->registrar->registerHooks($module)
        && $module->registrar->addExtensionHooks();
}