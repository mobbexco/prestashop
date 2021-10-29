<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_6_1($module)
{
    return $module->registerHooks();
}