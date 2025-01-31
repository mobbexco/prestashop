<?php

namespace Mobbex\PS\Checkout\Models;

if (!defined('_PS_VERSION_'))
    exit;

class Logger
{
    /**
     * Add log to PrestaShop log table.
     * Mode debug: Log data if debug mode is active
     * Mode error: Always log data.
     * Mode fatal: Always log data & stop code execution.
     * 
     * @param string $mode debug | error | fatal    
     * @param string $message
     * @param array $data
     * @param bool $die
     */
    public static function log($mode, $message, $data = [])
    {
        if (!Config::$settings['debug_mode'] && $mode === 'debug')
            return;

        \PrestaShopLogger::addLog(
            "Mobbex $mode: $message " . json_encode($data),
            in_array($mode, ['fatal', 'error']) ? 3 : 1,
            null,
            'Mobbex',
            str_replace('.', '', \Mobbex\PS\Checkout\Models\Config::MODULE_VERSION),
            true
        );

        if ($mode === 'fatal') {
            header("HTTP/1.1 500");
            die($message);
        }
    }
}
