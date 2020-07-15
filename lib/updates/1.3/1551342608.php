<?php

# # # # # # # # # # # # # # # # # # # # # # # #
#  This meta update replaces smarty variable  #
#  {$deal.description|escape}                 #
#  with the                                   #
#  {$deal.description_sanitized}              #
#  in the crm_notification table              #
# # # # # # # # # # # # # # # # # # # # # # # #

$nm = new crmNotificationModel();
$list = $nm->select('id, body')->where("event LIKE 'deal.%'")->fetchAll();

foreach ($list as $row) {

    if (!stripos($row['body'], '{$deal.description|escape}')) {
        continue;
    }

    $row['body'] = str_ireplace('{$deal.description|escape}', '{$deal.description_sanitized}', $row['body']);

    $nm->updateById($row['id'], array('body' => $row['body']));
}
