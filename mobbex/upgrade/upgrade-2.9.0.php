<?php

defined('_PS_VERSION_') || exit;

use Mobbex\PS\Checkout\Models\Config;

function upgrade_module_2_9_0(Mobbex $module) {
    Configuration::updateValue(Config::$settings['order_status_approved'], \Configuration::get('PS_OS_PAYMENT'));
    Configuration::updateValue(Config::$settings['order_status_failed'],   \Configuration::get('PS_OS_ERROR'));
    Configuration::updateValue(Config::$settings['order_status_refunded'], \Configuration::get('PS_OS_REFUND'));
    Configuration::updateValue(Config::$settings['order_status_rejected'], \Configuration::get('PS_OS_ERROR'));
}