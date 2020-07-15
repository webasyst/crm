<?php

$model = new waContactSettingsModel();
$model->deleteByField(array('app_id' => 'crm', 'name' => 'message_view_mode'));