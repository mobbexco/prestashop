<?php

defined('_PS_VERSION_') || exit;

function upgrade_module_3_0_0(Mobbex $module)
{
    return $module->_createTable() && $module->unregisterHooks() && $module->registerHooks() && $module->addExtensionHooks();
}