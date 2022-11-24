<?php

defined('_PS_VERSION_') || exit;

function upgrade_module_2_8_0(Mobbex $module)
{

    $configs = [
        'color'      => trim((string) \Configuration::get('MOBBEX_PLANS_TEXT_COLOR')),
        'background' => trim((string) \Configuration::get('MOBBEX_PLANS_BACKGROUND')),
        'padding'    => trim((string) \Configuration::get('MOBBEX_PLANS_PADDING')),
        'font-size'  => trim((string) \Configuration::get('MOBBEX_PLANS_FONT_SIZE')),
    ];

    // Migrate previus plan configs to new option
    \Configuration::updateValue($module->config->names['widget'],
'.mbbxWidgetOpenBtn {
    width: fit-content;
    min-height: 40px;
    border-radius: 6px;
    padding: ' . ($configs['padding'] ?: '8px 18px') . '; /* arriba/abajo, izquierda/derecha */
    font-size: ' . ($configs['font-size'] ?: '16px') . ';
    color: ' . ($configs['color'] ?: '#6f00ff') . '; 
    background: ' . ($configs['background'] ?: '#ffffff') . ';
    border: 2px solid #6f00ff; /* grosor, estilo de linea, color */
    cursor: pointer;
    /*box-shadow: 2px 2px 4px 0 rgba(0, 0, 0, .2);*/
}

.mbbxWidgetOpenBtn:hover {
    color: #ffffff;
    background-color: #6f00ff;
}');
    $registrar = new \Mobbex\PS\Checkout\Models\Registrar();
    return $module->createTables() && $registrar->unregisterHooks($module) && $registrar->registerHooks($module) && $registrar->addExtensionHooks();
}