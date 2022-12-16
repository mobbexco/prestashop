<?php

namespace Mobbex\PS\Checkout\Models;

class Sdk 
{
    public function __construct()
    {
        // Set platform information
        \Mobbex\Platform::init(
            'magento_2',
            $this->moduleResource->getDbVersion('Mobbex'),
            $this->_urlBuilder->getUrl('/'),
            [
                'magento' => $this->productMetadata->getVersion(),
                'webpay'  => $this->moduleResource->getDbVersion('Mobbex_Webpay'),
                'sdk'     => class_exists('\Composer\InstalledVersions') ? \Composer\InstalledVersions::getVersion('mobbexco/php-plugins-sdk') : '',
            ],
            $this->config->settings,
            ['\Mobbex\PS\Checkout\Models\Registrar', 'executeHook']
        );

        // Init api conector
        \Mobbex\Api::init();
    }
}