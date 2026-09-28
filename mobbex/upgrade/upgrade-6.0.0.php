<?php

defined('_PS_VERSION_') || exit;

use Mobbex\PS\Checkout\Models\Config;

/**
 * Upgrade module db data.
 *
 * IMPORTANT: Update the name of the function (and file) on every new release.
 *
 * This script runs on every upgrade towards 6.0.0 (e.g. from 5.x or older).
 * It is the interception point to block the upgrade when the running
 * PrestaShop version is not supported by this plugin version, since the
 * upgrade flow never calls Mobbex::install().
 *
 * @param \Mobbex $module Mobbex module instance.
 *
 * @return bool Upgrade result.
 */
function upgrade_module_6_0_0($module) {
    \Mobbex\PS\Checkout\Models\Logger::log('debug', 'Starting upgrade process to 6.0.0');

    // Bloquea el upgrade en versiones de PrestaShop no soportadas.
    //
    // IMPORTANTE: esta excepción se lanza FUERA del try/catch a propósito, para que
    // se propague por el stack. Devolver false NO alcanza: el module manager de Symfony
    // (ModuleDataUpdater::upgrade) llama a upgradeModuleVersion() justo despues de
    // runUpgradeModule(), subiendo la version de BD a 6.0.0 aunque el upgrade haya
    // devuelto false. Al propagar la excepcion se aborta el flujo antes de ese bump
    // forzado, asi el upgrade falla de verdad y la version de BD no avanza.
    if (version_compare(_PS_VERSION_, Config::MINIMUN_PS_VERSION, '<')) {
        \Mobbex\PS\Checkout\Models\Logger::log(
            'error',
            'Upgrade blocked: PrestaShop ' . _PS_VERSION_ . ' is lower than the required ' . Config::MINIMUN_PS_VERSION
        );
        method_exists($module, 'addError') ? $module->addError(
            "PrestaShop version not supported. This module requiere Prestashop " . Config::MINIMUN_PS_VERSION . " or newer"
        ) : false;

        throw new \PrestaShopException(
            "PrestaShop version not supported. This module requiere Prestashop " . Config::MINIMUN_PS_VERSION . " or newer"
        );
    }

    try {
        if (!$module->installer->createTables())
            throw new \Exception('Create tables failed');
        // ... resto del cuerpo igual (createStates, createCostProduct, hooks, return true) ...
    } catch (\Exception $e) {
        \Mobbex\PS\Checkout\Models\Logger::log('error', 'Upgrade ' . $e->getMessage());
        method_exists($module, 'addError') ? $module->addError($e->getMessage()) : false;
        return false;
    }
}

