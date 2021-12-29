<?php

defined('_PS_VERSION_') || exit;

function upgrade_module_2_7_2(Mobbex $module)
{
    return $module->_createTable() && $module->registerHooks() && $module->addExtensionHooks();
}