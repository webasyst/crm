<?php

# # # # # # # # # # # # # # # # # # # # # # # # # # # # # #
#  This meta update replaces the format of the last link  #
#  that the user of CRM visited in backend                #
#  Old format: /kekasyst/crm/call/                        #
#  New format: call/                                      #
# # # # # # # # # # # # # # # # # # # # # # # # # # # # # #

$csm = new waContactSettingsModel();
$sql = "SELECT *
        FROM {$csm->getTableName()}
        WHERE app_id = 'crm'
          AND name = 'last_url'";
$old_last_urls = $csm->query($sql)->fetchAll('contact_id');
foreach ($old_last_urls as $old_url) {
    if (preg_match('~\/\w+\/\w+\/(\w+)\/~', $old_url['value'], $matches)){
        $csm->set($old_url['contact_id'], 'crm', 'last_url', $matches[1].'/');
    }
}