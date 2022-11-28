<?php

defined('_PS_VERSION_') || exit;

function upgrade_module_2_7_2(Mobbex $module)
{
    $registrar = new \Mobbex\PS\Checkout\Models\Registrar();
    return $module->createTables() && $registrar->registerHooks($module) && $registrar->addExtensionHooks();
}