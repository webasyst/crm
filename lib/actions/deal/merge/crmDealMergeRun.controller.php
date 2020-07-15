<?php

class crmDealMergeRunController extends crmJsonController
{
    public function execute()
    {
        // get data with taking into account access to deals
        $data = $this->getData();
        $master_id = $data['master_id'];
        $slave_ids = $data['slave_ids'];

        if (!$master_id || !$slave_ids) {
            throw new waException('No deals to merge.');
        }

        // Merge
        self::merge($slave_ids, $master_id);
    }

    /**
     * Get data from request and apply access rights filter
     * @return array $data
     *      - int   $data['master_id']
     *      - int[] $data['slave_ids']
     * @throws waDbException
     * @throws waException
     */
    protected function getData()
    {
        $data = [
            'master_id' => null,
            'slave_ids' => []
        ];

        $master_id = waRequest::post('master_id', 0, waRequest::TYPE_INT);
        if ($master_id <= 0) {
            return $data;
        }

        $slave_ids = waRequest::post('slave_ids', array(), waRequest::TYPE_ARRAY_INT);
        if (!$slave_ids) {
            return $data;
        }

        $ids = array_merge([$master_id], array_values($slave_ids));
        $ids = $this->dropUnallowed($ids, [
            'level' => crmRightConfig::RIGHT_DEAL_ALL
        ]);

        foreach ($ids as $id) {
            if ($id == $master_id) {
                $data['master_id'] = $id;
            } else {
                $data['slave_ids'][] = $id;
            }
        }

        return $data;
    }


    /**
     * Merge given deals into master deal, save, then delete slaves.
     * @param array $merge_ids
     * @param int $master_id
     * @return array
     * @throws waException
     */
    protected static function merge($slave_ids, $master_id)
    {
//        $merge_ids[] = $master_id;
        $ids = array_flip($slave_ids);
        unset($ids[$master_id]);
        $slave_ids = array_flip($ids);

        $dm = new crmDealModel();
        $master = $dm->getById($master_id);
        if (!$master) {
            throw new waException('Deal not found');
        }
        $m = new waModel();
        $rm = new crmReminderModel();
        $nm = new crmNoteModel();
        $im = new crmInvoiceModel();
        $lm = new crmLogModel();
        $fm = new crmFileModel();
        $mm = new crmMessageModel();
        $cm = new crmCallModel();
        $dpm = new crmDealParticipantsModel();
        $cnm = new crmConversationModel();

        //
        // All the simple cases: update id in tables
        //
        foreach (array(
                     array($im->getTableName(), 'deal_id', ''),
                     array($mm->getTableName(), 'deal_id', ''),
                     array($cm->getTableName(), 'deal_id', ''),
                     array($rm->getTableName(), 'contact_id', '-'),
                     array($lm->getTableName(), 'contact_id', '-'),
                     array($nm->getTableName(), 'contact_id', '-'),
                     array($fm->getTableName(), 'contact_id', '-'),
                 ) as $row) {
            list($table, $field, $sign) = $row;
            $sql = "UPDATE $table SET $field = :master WHERE $field IN('".$sign.join("','".$sign, $m->escape($slave_ids))."')";
            $m->exec($sql, array('master' => $master_id * ($sign ? -1 : 1)));
        }
        $sql = "DELETE FROM {$dm->getTableName()} WHERE id IN('".join("','", $m->escape($slave_ids))."')";
        $m->exec($sql, array('master' => $master_id));

        $sql = "INSERT IGNORE INTO {$dpm->getTableName()} (deal_id, contact_id, role_id, label)
            SELECT DISTINCT ".$master_id.", contact_id, role_id, label FROM {$dpm->getTableName()}
                WHERE contact_id IN('".join("','", $m->escape($slave_ids))."')";
        $m->exec($sql);
        $sql = "DELETE FROM {$dpm->getTableName()} WHERE contact_id IN(:ids)";
        $m->exec($sql, array('ids' => $slave_ids));

        $sql = "UPDATE {$cnm->getTableName()} SET user_contact_id = :user_contact_id, deal_id = :master
            WHERE deal_id IN('".join("','", $m->escape($slave_ids))."')";
        $m->exec($sql, array('user_contact_id' => $master['user_contact_id'], 'master' => $master_id));

        $asm = new waAppSettingsModel();
        $asm->set('crm', 'call_ts', time());
    }

    /**
     * @param int[] $ids
     * @return int[]
     * @throws waDbException
     * @throws waException
     */
    protected function dropUnallowed($ids)
    {
        return $this->getCrmRights()->dropUnallowedDeals($ids, [
            'level' => crmRightConfig::RIGHT_DEAL_ALL
        ]);
    }
}
