<?php

defined('_PS_VERSION_') || exit;

function upgrade_module_3_0_0(Mobbex $module)
{
    $registrar = new \Mobbex\PS\Checkout\Models\Registrar();
    return $module->createTables() && $registrar->unregisterHooks($module) && $registrar->registerHooks($module) && $registrar->addExtensionHooks();
}