<?php

defined('_PS_VERSION_') || exit;

class MobbexTaskModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
        $this->logger = new \Mobbex\Logger();
    }

    public function postProcess()
    {
        // We don't do anything if the module has been disabled
        if ($this->module->active == false || !\Configuration::get('MOBBEX_CRON_MODE') || MobbexHelper::needUpgrade())
            $this->logger->debug('Task Controller Call On Module or CRON Inactive', $_REQUEST, true);

        die(MobbexTask::executePendingTasks() ? 'Tasks executed successfully' : 'Error executing one or more tasks');
    }
}