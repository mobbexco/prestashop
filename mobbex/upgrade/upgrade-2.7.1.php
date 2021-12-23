<?php

defined('_PS_VERSION_') || exit;

function upgrade_module_2_7_1($module)
{
    return $module->registerHooks() && $module->addExtensionHooks();
}