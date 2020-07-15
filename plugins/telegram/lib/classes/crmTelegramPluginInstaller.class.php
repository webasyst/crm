<?php

class crmTelegramPluginInstaller
{
    /**
     * @param string|array[] string $table
     */
    public function createTable($table)
    {
        $tables = array_map('strval', (array)$table);
        if (empty($tables)) {
            return;
        }

        $db_path = wa()->getAppPath('plugins/telegram/lib/config/db.php', 'crm');
        $db = include($db_path);

        if ($table === 'all') {
            $db_partial = $db;
        } else {
            $db_partial = array();
            foreach ($tables as $table) {
                if (isset($db[$table])) {
                    $db_partial[$table] = $db[$table];
                }
            }
        }

        if (empty($db_partial)) {
            return;
        }

        $m = new waModel();
        $m->createSchema($db_partial);
    }
}
