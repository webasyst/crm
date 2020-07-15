<?php

// !text -> !deal_description

$fpm = new crmFormParamsModel();
foreach ($fpm->select('*')->where('name = "fields"')->fetchAll() as $param) {
    $fields = @unserialize($param['value']);

    if (!is_array($fields)) {
        continue;
    }

    $changed = false;
    foreach ($fields as &$field) {
        if (!isset($field['id'])) {
            continue;
        }
        if ($field['id'] === '!text') {
            $field['id'] = '!deal_description';
            $changed = true;
            break;
        }
    }
    unset($field);

    if (!$changed) {
        continue;
    }

    $fpm->updateByField(array(
        'form_id' => $param['form_id'],
        'name' => $param['name']
    ), array(
        'value' => serialize($fields)
    ));
}
