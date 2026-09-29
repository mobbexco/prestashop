<?php

defined('_PS_VERSION_') || exit;

use Mobbex\PS\Checkout\Models\Config;

function upgrade_module_2_9_0(Mobbex $module) {
    // Block the upgrade on unsupported PrestaShop versions.
    if (version_compare(_PS_VERSION_, Config::MINIMUN_PS_VERSION, '<')) {
        method_exists($module, 'addError') ? $module->addError("PrestaShop version not supported. This module requiere Prestashop " . Config::MINIMUN_PS_VERSION . " or newer") : false;
        return false;
    }

    Configuration::updateValue(Config::$settings['order_status_approved'], \Configuration::get('PS_OS_PAYMENT'));
    Configuration::updateValue(Config::$settings['order_status_failed'],   \Configuration::get('PS_OS_ERROR'));
    Configuration::updateValue(Config::$settings['order_status_refunded'], \Configuration::get('PS_OS_REFUND'));
    Configuration::updateValue(Config::$settings['order_status_rejected'], \Configuration::get('PS_OS_ERROR'));
}