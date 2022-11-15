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
     * Add log to PrestaShop log table. Log errors & debug data if debug mode is active
     * 
     * @param string $mode Modes: 'error'|'debug'
     * @param string $message
     * @param array $data
     * @param bool $die
     */
    public function debug($mode, $message, $data = [], $die = false)
    {
        if (!$this->config->settings['debug_mode'] && $mode === 'debug')
            return;

        \PrestaShopLogger::addLog(
            "Mobbex $mode: $message" . json_encode($data),
            $mode === 'error' ? 3 : 1,
            null,
            'Mobbex',
            str_replace('.', '', \Mobbex\Config::MODULE_VERSION),
            true
        );

        if ($die)
            die($message);
    }
}
