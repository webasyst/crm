<?php

$tm = new crmTemplatesModel();
$tpm = new crmTemplatesParamsModel();

if ($tm->select('count(*)')->where('origin_id IS NOT NULL')->fetchField() <= 0) {
    $fields = $tm->getMetadata();
    $templates = crmTemplates::getTemplatesVariants(true);
    $cnt = $tm->countAll();
    $lock_name = 'crm_new_templates';
    try {
        $tm->exec("SELECT GET_LOCK(?, -1)", [ $lock_name ]);
    } catch (Exception $e) {
        return;
    }
    try {
        foreach ($templates as $t) {
            $cnt++;
            $t['name'] = _w('Template') . ' ' . $cnt;
            $data = array_intersect_key($t, $fields);
            $template_id = $tm->insert($data);

            if ($template_id) {
                foreach ($t['template_params'] as &$param) {
                    $param['template_id'] = $template_id;
                }
                $tpm->multipleInsert($t['template_params']);
            }
        }
        $tm->exec("SELECT RELEASE_LOCK(?)", [ $lock_name ]);
    } catch (Exception $ex) {
        $tm->exec("SELECT RELEASE_LOCK(?)", [ $lock_name ]);    
    }
}