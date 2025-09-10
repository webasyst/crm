<?php

$forms = (new crmFormModel)->getAllFormsForControllers();

$pm = new crmFormParamsModel();

foreach ($forms as $form) {
    $pm->setOne($form['id'], 'antibot_honey_pot', @serialize([
        'empty_field_name'   => '!f' . waUtils::getRandomHexString(6),
        'filled_field_name'  => '!f' . waUtils::getRandomHexString(6),
        'filled_field_value' => waUtils::getRandomHexString(32),
    ]));
}
