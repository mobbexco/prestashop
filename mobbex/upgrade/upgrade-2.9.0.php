<?php

defined('_PS_VERSION_') || exit;

function upgrade_module_2_9_0(Mobbex $module)
{
    Configuration::updateValue(MobbexHelper::K_ORDER_STATUS_APPROVED, \Configuration::get('PS_OS_PAYMENT'));
    Configuration::updateValue(MobbexHelper::K_ORDER_STATUS_FAILED,   \Configuration::get('PS_OS_ERROR'));
    Configuration::updateValue(MobbexHelper::K_ORDER_STATUS_REFUNDED, \Configuration::get('PS_OS_REFUND'));
    Configuration::updateValue(MobbexHelper::K_ORDER_STATUS_REJECTED, \Configuration::get('PS_OS_ERROR'));

    return $module->_createTable() && $module->unregisterHooks() && $module->registerHooks() && $module->addExtensionHooks();
}