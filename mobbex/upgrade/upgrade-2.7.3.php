<?php

defined('_PS_VERSION_') || exit;

function upgrade_module_2_7_3(Mobbex $module)
{
    return $module->createTables() && $module->registerHooks() && $module->addExtensionHooks();
}