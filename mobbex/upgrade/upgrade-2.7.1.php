<?php

defined('_PS_VERSION_') || exit;

function upgrade_module_2_7_1($module)
{
    $registrar = new \Mobbex\Registrar();
    return $registrar->registerHooks($module) && $registrar->addExtensionHooks();
}