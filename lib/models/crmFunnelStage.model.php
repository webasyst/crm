<?php

class crmFunnelStageModel extends crmModel
{
    protected $table = 'crm_funnel_stage';

    /**
     * @param $funnels
     * @return array
     */
    public function withStages($funnels)
    {
        if (!$funnels) {
            return array();
        }
        $stages_iterator = $this->select('*')->where('funnel_id IN (?)', array(array_keys($funnels)))->order('number')->query();
        foreach($stages_iterator as $s) {
            if (!empty($funnels[$s['funnel_id']])) {
                $funnels[$s['funnel_id']]['stages'][$s['id']] = $s;
            }
        }
        foreach ($funnels as &$f) {
            $f['stages'] = ifempty($f['stages'], array());
            $f['stages'] = $this->getStagesByFunnel($f, true);
        }
        return $funnels;
    }

    /**
     * @param array|int $funnel
     * @param bool $workup
     * @return array
     */
    public function getStagesByFunnel($funnel, $workup = true)
    {
        if (is_array($funnel) && isset($funnel['id'])) {
            $input_type = 'record';
            $funnel_id = (int)$funnel['id'];
        } elseif (wa_is_int($funnel) && $funnel > 0) {
            $input_type = 'int';
            $funnel_id = (int)$funnel;
        } else {
            $input_type = 'int';
            $funnel_id = 0;
        }

        if (!empty($funnel['stages']) && is_array($funnel['stages'])) {
            $stages = $funnel['stages'];
        } else {
            $stages = $this->select('*')->where('funnel_id = ?', $funnel_id)->order('number')->fetchAll('id');
        }

        if ($workup) {
            $i = 0;
            if ($input_type === 'int') {
                $fm = new crmFunnelModel();
                $funnel = $fm->getById($funnel_id);
            }
            foreach ($stages as $id => &$s) {
                $s['color'] = crmFunnel::getFunnelStageColor($funnel['open_color'], $funnel['close_color'], $i, count($stages));
                $i++;
            }
            unset($s);
        }
        return $stages;
    }
}
