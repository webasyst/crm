<?php

/**
 * Update all tables when several contacts are merged into one.
 */
class crmContactsMergeHandler extends waEventHandler
{
    public function execute(&$params)
    {
        $master_id = $params['id'];
        $merge_ids = (array)$params['contacts'];

        $m = new waModel();
        $dm = new crmDealModel();
        $im = new crmInvoiceModel();
        $rm = new crmReminderModel();
        $lm = new crmLogModel();
        $nm = new crmNoteModel();
        $fm = new crmFileModel();
        $mm = new crmMessageModel();
        $cm = new crmCallModel();
        $ctm = new crmContactTagsModel();
        $dpm = new crmDealParticipantsModel();
        $cnm = new crmConversationModel();
        $mrm = new crmMessageRecipientsModel();

        //
        // All the simple cases: update contact_id in tables
        //
        foreach (array(
            array($dm->getTableName(), 'contact_id'),
            array($dm->getTableName(), 'creator_contact_id'),
            array($dm->getTableName(), 'user_contact_id'),
            //array($dpm->getTableName(), 'contact_id'),
            array($im->getTableName(), 'contact_id'),
            array($im->getTableName(), 'creator_contact_id'),
            array($rm->getTableName(), 'contact_id'),
            array($rm->getTableName(), 'creator_contact_id'),
            array($rm->getTableName(), 'user_contact_id'),

            array($lm->getTableName(), 'contact_id'),
            array($lm->getTableName(), 'actor_contact_id'),
            array($nm->getTableName(), 'contact_id'),
            array($fm->getTableName(), 'contact_id'),
            array($fm->getTableName(), 'creator_contact_id'),
            array($mm->getTableName(), 'contact_id'),
            array($mm->getTableName(), 'creator_contact_id'),
            array($cm->getTableName(), 'client_contact_id'),
            array($cm->getTableName(), 'user_contact_id'),
            array($cnm->getTableName(), 'contact_id'),
            array($cnm->getTableName(), 'user_contact_id'),
            array($mrm->getTableName(), 'contact_id'),

        ) as $pair) {
            list($table, $field) = $pair;
            $sql = "UPDATE $table SET $field = :master WHERE $field IN(:ids)";
            $m->exec($sql, array('master' => $master_id, 'ids' => $merge_ids));
        }
        $sql = "INSERT IGNORE INTO {$ctm->getTableName()} (contact_id, tag_id)
            SELECT DISTINCT ".$master_id.", tag_id FROM {$ctm->getTableName()}
                WHERE contact_id IN('".join("','", $m->escape($merge_ids))."')";
        $m->exec($sql);
        $sql = "DELETE FROM {$ctm->getTableName()} WHERE contact_id IN(:ids)";
        $m->exec($sql, array('ids' => $merge_ids));

        $sql = "INSERT IGNORE INTO {$dpm->getTableName()} (deal_id, contact_id, role_id, label)
            SELECT DISTINCT deal_id, ".$master_id.", role_id, label FROM {$dpm->getTableName()}
                WHERE contact_id IN('".join("','", $m->escape($merge_ids))."')";
        $m->exec($sql);
        $sql = "DELETE FROM {$dpm->getTableName()} WHERE contact_id IN(:ids)";
        $m->exec($sql, array('ids' => $merge_ids));

        /*
        $tags = $ctm->select('*')->where("contact_id IN('".(int)$master_id."','".join("','", $ctm->escape($merge_ids))."')")->fetchAll();
        foreach ($tags as $t1) {
            if ($t1['contact_id'] != $master_id) {
                foreach ($tags as $t2) {
                    if ($t2['contact_id'] == $master_id && $t2['tag_id'] == $t1['tag_id']) {
                        continue 2;
                    }
                }
                $ctm->insert(array('contact_id' => $master_id, 'tag_id' => $t1['tag_id']));
            }
        }
        */

        $asm = new waAppSettingsModel();
        $asm->set('crm', 'call_ts', time());

        return null;
    }
}
