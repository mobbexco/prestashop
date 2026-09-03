<?php

namespace Mobbex\PS\Checkout\Models;

if (!defined('_PS_VERSION_'))
    exit;

class Logger
{
    /** Keys that must never be written to the PrestaShop log table in clear text. */
    private static $sensitiveKeys = ['mbbx_token', 'hash', 'token'];

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

        $sanitizedData = self::sanitize($data);
        \PrestaShopLogger::addLog(
            "Mobbex $mode: $message " . json_encode($sanitizedData),
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

    /**
     * Recursively redact known sensitive keys before logging.
     *
     * @param mixed $data
     * @return mixed
     */
    private static function sanitize($data)
    {
        if (!is_array($data))
            return $data;

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sanitize($value);
            } elseif (is_string($key) && in_array(strtolower($key), self::$sensitiveKeys, true)) {
                $data[$key] = '***REDACTED***';
            }
        }

        return $data;
    }
}
