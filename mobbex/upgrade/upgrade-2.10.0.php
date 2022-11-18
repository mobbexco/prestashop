<?php

defined('_PS_VERSION_') || exit;

function upgrade_module_2_10_0(Mobbex $module)
{
    return $module->createTables() && $module->unregisterHooks() && $module->registerHooks() && $module->addExtensionHooks();
}