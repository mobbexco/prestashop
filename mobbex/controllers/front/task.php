<?php

defined('_PS_VERSION_') || exit;

use Mobbex\PS\Checkout\Models\Logger;

class MobbexTaskModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        // We don't do anything if the module has been disabled
        if ($this->module->active == false || !\Configuration::get('MOBBEX_CRON_MODE') || \Mobbex\PS\Checkout\Models\Updater::needUpgrade())
            Logger::log('fatal', 'task > postProcess | Task Controller Call On Module or CRON Inactive', $_REQUEST);

        die(\Mobbex\PS\Checkout\Models\Task::executePendingTasks() ? 'Tasks executed successfully' : 'Error executing one or more tasks');
    }
}
