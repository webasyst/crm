<?php

# # # # # # # # # # # # # # # # # # # # # # # #
#  This meta update replaces smarty variable  #
#  {$company.address|escape}                  #
#  with the                                   #
#  {$company.address|escape|nl2br}            #
#  in the crm_template table                  #
# # # # # # # # # # # # # # # # # # # # # # # #

$tm = new crmTemplatesModel();
$list = $tm->select('id, content')->fetchAll();

foreach ($list as $row) {

    if (!stripos($row['content'], '{$company.address|escape}')) {
        continue;
    }

    $row['content'] = str_ireplace('{$company.address|escape}', '{$company.address|escape|nl2br}', $row['content']);

    $tm->updateById($row['id'], array('content' => $row['content']));
}
