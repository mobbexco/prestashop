<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_5_0($module)
{
    $module->_alterTable();
    return true;
}