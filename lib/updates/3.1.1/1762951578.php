<?php

$m = new crmLogModel();

if (empty($m->query("SELECT * FROM wa_contact_data_text WHERE field='ban_reason' LIMIT 1")->fetch())) {
    $ids = array_keys($m->query("SELECT MAX(l.id) max_id 
        FROM crm_log l 
        INNER JOIN wa_contact k ON k.id=l.contact_id AND k.is_user=-1 
        WHERE l.action='contact_ban' 
        GROUP BY l.contact_id")->fetchAll('max_id'));
    $log = $m->getById($ids);

    if (!empty($log)) {
        $log = array_map(function ($el) {
            $params = json_decode($el['params'], true);
            if (!empty($params['reason'])) {
                return [
                    'contact_id' => $el['contact_id'],
                    'field' => 'ban_reason',
                    'value' => $params['reason'],
                    'sort' => 0
                ];
            }
            return null;
        }, $log);

        $log = array_filter($log);

        if (!empty($log)) {
            (new waContactDataTextModel)->multipleInsert(array_values($log), waModel::INSERT_IGNORE);
        }
    }
}
