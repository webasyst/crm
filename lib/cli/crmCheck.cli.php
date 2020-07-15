<?php

/*
 * Check all undone tasks
 *
 * Usage example: php cli.php crm check
 */

class crmCheckCli extends waCliController
{
    public function execute()
    {
        /**
         * @event start_check
         * @return bool
         */
        wa('crm')->event('start_check');
    }
}
