<?php

$m = new crmFormParamsModel();
foreach ($m->query(
    "SELECT * 
      FROM `crm_form_params` 
      WHERE `name` = 'fields' AND `value` 
      LIKE 's:%'") as $item)
{
    $item['value'] = unserialize($item['value']);
    $m->updateByField(array(
        'form_id' => $item['form_id'],
        'name' => $item['name']
    ), array(
        'value' => $item['value']
    ));
}
