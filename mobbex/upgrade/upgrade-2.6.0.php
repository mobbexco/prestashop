<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_6_0($module)
{
    $registrar = new \Mobbex\Registrar();
    return $registrar->registerHooks($module);
}