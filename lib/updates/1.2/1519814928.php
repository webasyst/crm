<?php

$spm = new crmSourceParamsModel();
$sm = new crmSourceModel();
$ids = $sm->select('id')->where('funnel_id IS NOT NULL')->fetchAll(null, true);
foreach ($ids as $id) {
    $spm->setOne($id, 'create_deal', '1');
}
