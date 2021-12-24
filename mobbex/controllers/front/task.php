<?php

defined('_PS_VERSION_') || exit;

class MobbexTaskModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        // We don't do anything if the module has been disabled
        if ($this->module->active == false || !\Configuration::get('MOBBEX_CRON_MODE'))
            MobbexHelper::log('Task Controller Call On Module or CRON Inactive', $_REQUEST, true, true);

        MobbexTask::executePendingTasks();
    }
}