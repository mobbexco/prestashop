<?php

namespace Mobbex;

if (!defined('_PS_VERSION_'))
    exit;

class Config
{
    const MODULE_VERSION = '4.0.0';
    const PS16           = '1.6';
    const PS17           = '1.7';

    public $settings      = [];
    public $default       = [];

    //Mobbex Order Status
    public $orderStatuses = [
        'mobbex_status_approved' => ['name' => 'MOBBEX_OS_APPROVED', 'label' => 'Transaction in Process', 'color' => '#5bff67', 'send_email' => true],
        'mobbex_status_pending'  => ['name' => 'MOBBEX_OS_PENDING', 'label' => 'Pending', 'color' => '#FEFF64', 'send_email' => false],
        'mobbex_status_waiting'  => ['name' => 'MOBBEX_OS_WAITING', 'label' => 'Waiting', 'color' => '#FEFF64', 'send_email' => false],
        'mobbex_status_rejected' => ['name' => 'MOBBEX_OS_REJECTED' ,'label' => 'Rejected Payment', 'color' => '#8F0621', 'send_email' => false],
    ];

    public function __construct()
    {
        $this->settings = $this->getSettings();
    }

    /**
     * Returns an array of config options for prestashop module config.
     * @param bool $extensionOptions 
     */
    public function getConfigForm($extensionOptions = true)
    {
        $form = require __DIR__ . '/../utils/config-form.php';
        return $extensionOptions ? \MobbexHelper::executeHook('displayMobbexConfiguration', true, $form) : $form;
    }

    /**
     * Get the Mobbex module settigns from config form array.
     *
     * @param string $key specifies the key used in the array that method returns
     * @return array $settings 
     */
    public function getSettings($key = 'key')
    {
        $settings = [];

        foreach ($this->getConfigForm()['form']['input'] as $input)
            $settings[$input[$key]]  = !\Configuration::get($input['name']) ? $input['default'] : \Configuration::get($input['name']);

        return $settings;
    }

    /**
     * Delete all the mobbex settings from the prestashop database.
     */
    public function deleteSettings()
    {
        foreach ($this->getConfigForm()['form']['input'] as $setting)
            \Configuration::deleteByName($setting['name']);
    }

    /**
     * Used to translate a given label.
     * @param string $string
     * @param string $source
     */
    public function l($string, $source = 'mobbex')
    {
        return \Translate::getModuleTranslation(
            'mobbex',
            $string,
            $source,
        );
    }
}