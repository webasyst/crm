<?php

class crmDealChangeFunnelRunController extends crmJsonController
{
    public function execute()
    {
        // Ids of contacts to merge
        $funnel_id = waRequest::request('funnel_id', 0, waRequest::TYPE_INT);
        $stage_id = waRequest::request('stage_id', 0, waRequest::TYPE_INT);
        $deal_ids = waRequest::request('deal_ids', array(), waRequest::TYPE_ARRAY_INT);

        $deal_ids = $this->dropUnallowed($deal_ids);

        if (!$deal_ids || !$funnel_id || !$stage_id) {
            throw new waException('No deals to process.');
        }

        $this->change($deal_ids, $funnel_id, $stage_id);
    }

    /**
     * Change funnel/stage for given deals
     * @param array $deal_ids
     * @return array
     * @throws waException
     */
    protected function change($deal_ids, $funnel_id, $stage_id)
    {
        $dm = new crmDealModel();
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $lm = new crmLogModel();

        $deals = $dm->select('*')->where("id IN('".join("','", $dm->escape($deal_ids))."')")->fetchAll('id');
        $funnel = $fm->getById($funnel_id);
        $stage = $fsm->getById($stage_id);

        if (!$deals || !$funnel || !$stage || $stage['funnel_id'] != $funnel_id) {
            throw new waException('No deals to process.');
        }
        $sql = "UPDATE {$dm->getTableName()} SET funnel_id = :funnel_id, stage_id = :stage_id
            WHERE id IN('".join("','", $dm->escape($deal_ids))."')";
        $dm->exec($sql, array('funnel_id' => $funnel_id, 'stage_id' => $stage_id));

        $all_funnels = $fm->getAll('id');
        $all_stages = $fsm->getAll('id');

        foreach ($deals as $d) {
            $params = [];
            if ($d['funnel_id'] == $funnel_id) {
                $action_id = 'deal_step';
                $before = ifempty($all_stages[$d['stage_id']]['name'], $d['stage_id']);
                $after = $stage['name'];
                $params = [
                    'stage_id_before' => ifempty($all_stages[$d['stage_id']]['id'], $d['stage_id']),
                    'stage_id_after' => $stage['id']
                ];
            } else {
                $action_id = 'deal_move';
                $before = ifempty($all_funnels[$d['funnel_id']]['name'], $d['funnel_id']).'/'
                    .ifempty($all_stages[$d['stage_id']]['name'], $d['stage_id']);
                $after = $funnel['name'].'/'.$stage['name'];
            }
            $lm->log(
                $action_id,
                $d['id'] * -1,
                $d['id'],
                $before,
                $after,
                null,
                $params
            );

            $data = array(
                'funnel_id' => $funnel_id,
                'stage_id'  => $stage_id,
            );
            $event_data = array('deal' => $data + $d);
            /**
             * @event deal_move
             * @param array $deal
             * @return bool
             */
            wa('crm')->event('deal_move', $event_data);
        }
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
            'level' => crmRightConfig::RIGHT_DEAL_EDIT
        ]);
    }
}
