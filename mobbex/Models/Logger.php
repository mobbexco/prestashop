<?php

namespace Mobbex;

if (!defined('_PS_VERSION_'))
    exit;

class Logger
{
    public function __construct()
    {
        $this->config = new \Mobbex\Config();
    }

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
    public function log($mode, $message, $data = [])
    {
        \PrestaShopLogger::addLog(
            "Mobbex $mode: $message " . json_encode($data),
            $mode === 'error' ? 3 : 1,
            null,
            'Mobbex',
            str_replace('.', '', \Mobbex\Config::MODULE_VERSION),
            true
        );

        if ($mode === 'fatal')
            die($message);
    }
}
