<?php
if (!defined('_PS_VERSION_')) {
    exit;
}


function upgrade_module_2_4_4($module)
{
    
    $module->_alterTable();

    return true;
}