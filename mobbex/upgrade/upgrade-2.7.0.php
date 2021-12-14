<?php

defined('_PS_VERSION_') || exit;

function upgrade_module_2_7_0($module)
{
    return $module->registerHooks() && $module->addExtensionHooks();
}