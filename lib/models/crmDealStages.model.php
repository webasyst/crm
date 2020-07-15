<?php

class crmDealStagesModel extends crmModel
{
    protected $table = 'crm_deal_stages';

    public function open($deal_id, $stage_id, $old_stage_id = null)
    {
        $last = null;
        if ($old_stage_id) {
            $fsm = new crmFunnelStageModel();
            $stages = $fsm->select('*')->where('id IN('.intval($stage_id).','.intval($old_stage_id).')')->fetchAll('id');
            if ($stages[$old_stage_id]['number'] > $stages[$stage_id]['number']) {
                $last = $this->select('*')->where('deal_id='.intval($deal_id).' AND stage_id='.intval($stage_id))->order('id DESC')->limit(1)->fetchAssoc();
            }
        }
        if (!$last) {
            $this->insert(array(
                'deal_id'     => $deal_id,
                'stage_id'    => $stage_id,
                'in_datetime' => date('Y-m-d H:i:s'),
            ));
        } else {
            $this->updateById($last['id'], array(
                'out_datetime' => null,
                'minutes' => null,
            ));
        }
    }

    public function close($deal_id, $stage_id)
    {
        $stage = $this->select('*')->where(
            "deal_id=".intval($deal_id)." AND stage_id=".intval($stage_id)." AND out_datetime IS NULL"
        )->fetchAssoc();
        if ($stage) {
            $minutes = (time() - strtotime($stage['in_datetime'])) / 60;
            $this->updateById($stage['id'], array('out_datetime' => date('Y-m-d H:i:s'), 'minutes' => $minutes));
        }
    }

    public function getChartMin($chart_params, $stages, $threshold = 1)
    {
        $condition = "d.funnel_id = ".intval($chart_params['funnel_id']);
        if ($chart_params['user_id'] != 'all') {
            $condition .= ' AND d.user_contact_id = '.(int)$chart_params['user_id'];
        }

        $sql = "SELECT s.stage_id, AVG(s.minutes) min FROM {$this->getTableName()} s
            INNER JOIN crm_deal d ON d.id = s.deal_id
            WHERE $condition AND s.out_datetime >= '".$this->escape($chart_params['start_date'])
            ."' AND s.out_datetime <= '".$this->escape($chart_params['end_date'])
            ."' AND s.minutes IS NOT NULL AND s.minutes > ".intval($threshold)." GROUP BY s.stage_id";

        $res = $this->query($sql)->fetchAll();

        $sql1 = "SELECT s.stage_id, COUNT(s.id) cnt FROM {$this->getTableName()} s
            INNER JOIN crm_deal d ON d.id = s.deal_id
            WHERE $condition AND s.out_datetime >= '".$this->escape($chart_params['start_date'])
            ."' AND s.out_datetime <= '".$this->escape($chart_params['end_date'])."' GROUP BY s.stage_id";

        $res1 = $this->query($sql1)->fetchAll();

        $sql2 = "SELECT s.stage_id, COUNT(s.id) cnt FROM {$this->getTableName()} s
            INNER JOIN crm_deal d ON d.id = s.deal_id
            WHERE $condition AND s.out_datetime >= '".$this->escape($chart_params['start_date'])
            ."' AND s.out_datetime <= '".$this->escape($chart_params['end_date'])
            ."' AND s.out_datetime IS NOT NULL AND s.overdue_datetime IS NOT NULL GROUP BY s.stage_id";

        $res2 = $this->query($sql2)->fetchAll();

        $chart = array();
        $is_empty = true;

        foreach ($stages as $id => $s) {
            $point = array(
                'stage_id'  => $id,
                'value'     => 0,
                'over_text' => '',
                'base_text' => '',
                'sub_text'  => '',
            );
            foreach ($res as $r) {
                if ($r['stage_id'] == $id) {
                    $point['value'] = round($r['min']);
                    $point['over_text'] = crmHelper::titleMinutes($point['value']);
                    if ($point['value']) {
                        $is_empty = false;
                    }
                }
            }
            foreach ($res1 as $r) {
                if ($r['stage_id'] == $id) {
                    $point['base_text'] = sprintf_wp('%d pcs.', $r['cnt']);
                }
            }
            foreach ($res2 as $r) {
                if ($r['stage_id'] == $id) {
                    $point['sub_text'] = sprintf_wp('%d pcs.', $r['cnt']);
                }
            }
            $chart[$id] = $point;
        }

        return array('name' => 'min by stages', 'data' => array_values($chart), 'exists' => !$is_empty);
    }

    public function getOverdueNow($chart_params, $stages)
    {
        $condition = "d.funnel_id = ".intval($chart_params['funnel_id']);
        if ($chart_params['user_id'] != 'all') {
            $condition .= ' AND d.user_contact_id = '.(int)$chart_params['user_id'];
        }

        $sql1 = "SELECT s.stage_id, COUNT(s.id) cnt FROM {$this->getTableName()} s
            INNER JOIN crm_deal d ON d.id = s.deal_id
            WHERE $condition GROUP BY s.stage_id";

        $res1 = $this->query($sql1)->fetchAll();

        $sql2 = "SELECT s.stage_id, COUNT(s.id) cnt FROM {$this->getTableName()} s
            INNER JOIN crm_deal d ON d.id = s.deal_id
            WHERE $condition
            AND s.out_datetime IS NULL AND s.overdue_datetime IS NOT NULL GROUP BY s.stage_id";

        $res2 = $this->query($sql2)->fetchAll();

        $chart_total = $chart_overdue_now = array();

        $is_empty1 = $is_empty2 = true;

        foreach ($stages as $id => $s) {
            $point = array(
                'id'    => $id,
                'name'  => $s['name'],
                'value' => 0,
                'over_text' => '',
            );
            foreach ($res1 as $r) {
                if ($r['stage_id'] == $id) {
                    $point['value'] = round($r['cnt']);
                    $point['over_text'] = sprintf_wp('%d pcs.', $point['value']);
                    if ($point['value']) {
                        $is_empty1 = false;
                    }
                }
            }
            $chart_total[$id] = $point;
        }
        foreach ($stages as $id => $s) {
            $point = array(
                'stage_id'  => $id,
                'value'     => 0,
                'over_text' => '',
            );
            foreach ($res2 as $r) {
                if ($r['stage_id'] == $id) {
                    $point['value'] = round($r['cnt']);
                    $point['over_text'] = sprintf_wp('%s pcs.', $point['value']);
                    if ($point['value']) {
                        $is_empty2 = false;
                    }
                }
            }
            $chart_overdue_now[$id] = $point;
        }

        return array(
            array('name' => 'total', 'data' => array_values($chart_total), 'exists' => !$is_empty1),
            array('name' => 'overdue now', 'data' => array_values($chart_overdue_now), 'exists' => !$is_empty2),
        );
    }

    public function getOverdue()
    {
        $sql = "SELECT d.*, fs.limit_hours, ds.in_datetime, ds.out_datetime, ds.id deal_stage_id
            FROM {$this->getTableName()} ds
            INNER JOIN crm_funnel_stage fs ON fs.id = ds.stage_id
            INNER JOIN crm_deal d ON d.id = ds.deal_id
            INNER JOIN crm_funnel f ON f.id = d.funnel_id
            WHERE ds.out_datetime IS NULL AND ds.overdue_datetime IS NULL AND fs.limit_hours IS NOT NULL
            AND in_datetime < '".date('Y-m-d H:i:s')."' - INTERVAL fs.limit_hours HOUR";

        return $this->query($sql)->fetchAll();
    }
}
