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
     * @param $slave_ids
     * @param int $master_id
     * @return void
     * @throws waException
     */
    protected static function merge($slave_ids, $master_id)
    {
        $ids = array_flip($slave_ids);
        unset($ids[$master_id]);
        $slave_ids = array_flip($ids);

        $dm = new crmDealModel();
        $master = $dm->getById($master_id);
        if (!$master) {
            throw new waException(_w('Deal not found'));
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
        $cdpm = new crmDealParamsModel();

        /** All the simple cases: update id in tables */
        $cases = [
            array($im->getTableName(), 'deal_id', ''),
            array($mm->getTableName(), 'deal_id', ''),
            array($cm->getTableName(), 'deal_id', ''),
            array($rm->getTableName(), 'contact_id', '-'),
            array($lm->getTableName(), 'contact_id', '-'),
            array($nm->getTableName(), 'contact_id', '-'),
            array($fm->getTableName(), 'contact_id', '-'),
        ];
        foreach ($cases as $row) {
            list($table, $field, $sign) = $row;
            $m->exec("
                UPDATE $table SET $field = :master 
                WHERE $field IN (:ids)
            ", [
                'master' => $master_id * ($sign ? -1 : 1),
                'ids' => ($sign ? array_map(function ($v) {return abs($v) * -1;}, $slave_ids) : $slave_ids)
            ]);
        }

        /** Update deal master params */
        $params = $cdpm->get(array_merge([$master_id], $slave_ids));
        $params = self::mergeDealMasterParams($master_id, $slave_ids, $params);
        if ($params) {
            $cdpm->set([$master_id], $params);
        }

        /** Clear slave params */
        $m->exec("
            DELETE FROM {$cdpm->getTableName()} 
            WHERE deal_id IN (:slave_ids)
        ", [
            'slave_ids' => $slave_ids
        ]);

        /** Delete slave deals */
        $m->exec("
            DELETE FROM {$dm->getTableName()} 
            WHERE id IN (:slave_ids)
        ", [
            'slave_ids' => $slave_ids
        ]);

        $m->exec("
            INSERT IGNORE INTO {$dpm->getTableName()} (deal_id, contact_id, role_id, label)
            SELECT DISTINCT ".$master_id.", contact_id, role_id, label FROM {$dpm->getTableName()}
            WHERE contact_id IN (:slave_ids)
        ", [
            'slave_ids' => $slave_ids
        ]);

        $m->exec("
            DELETE FROM {$dpm->getTableName()} 
            WHERE contact_id IN (:ids)
        ", [
            'ids' => $slave_ids
        ]);

        $m->exec("
            UPDATE {$cnm->getTableName()} 
            SET user_contact_id = :user_contact_id, deal_id = :master
            WHERE deal_id IN (:slave_ids)
        ", [
            'user_contact_id' => $master['user_contact_id'],
            'master' => $master_id,
            'slave_ids' => $slave_ids
        ]);

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

    /**
     * @param $master_id
     * @param $slave_ids
     * @param $params
     * @return array
     */
    private static function mergeDealMasterParams($master_id, $slave_ids, $params)
    {
        $master_params = ifset($params, $master_id, []);
        unset($params[$master_id]);

        foreach ((array) $params as $_params) {
            $master_params += $_params;
        }

        return $master_params;
    }
}
