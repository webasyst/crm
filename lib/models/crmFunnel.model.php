<?php

class crmFunnelModel extends crmModel
{
    protected $table = 'crm_funnel';

    /**
     * Take into account rights
     * @return array
     */
    public function getAllFunnels($include_archived = false)
    {
        static $funnels = null;
        if ($funnels === null) {
            $funnels = array();
            $list = $this->select('*')->order('sort,name')->fetchAll('id');
            foreach ($list as $id => $f) {
                if ($this->getCrmRights()->funnel($f)) {
                    $funnels[$id] = $f;
                }
            }
        }
        if (!$include_archived) {
            return array_filter($funnels, function($f) {
                return !$f['is_archived'];
            });
        }
        return $funnels;
    }

    public function getAvailableFunnel($include_archived = false, $contact_id = null)
    {
        $contact_id = $contact_id ? $contact_id : wa()->getUser()->getId();
        $result_set = $this->select('*');
        if (!$include_archived) {
            $result_set = $result_set->where('is_archived=0');
        }
        $list = $result_set->order('sort,name')->fetchAll('id');
        $rights = new crmRights([ 'contact' => $contact_id ]);
        $stored_funnel_id = wa()->getUser()->getSettings('crm', 'deal_funnel_id');
        if ($stored_funnel_id && !empty($list[$stored_funnel_id]) && $rights->funnel($list[$stored_funnel_id])) {
            return $list[$stored_funnel_id];
        }
        foreach ($list as $id => $f) {
            if ($rights->funnel($f)) {
                return $f;
            }
        }
        return null;
    }

    /**
     * Fix "broken" colors in funnel record
     * @param array $funnel
     * @return array
     */
    public function fixFunnelColors($funnel)
    {
        if (!is_array($funnel) || empty($funnel) || !isset($funnel['id']) || $funnel['id'] <= 0) {
            return $funnel;
        }

        $update = [];
        foreach (['color', 'open_color', 'close_color'] as $field_id) {
            $color = ifset($funnel[$field_id]);
            $funnel[$field_id] = $this->typecastColor($color);
            if ($funnel[$field_id] !== $color) {
                $update[$field_id] = $funnel[$field_id];
            }
        }

        if ($update) {
            $this->updateById($funnel['id'], $update);
        }

        return $funnel;
    }

    protected function typecastColor($color)
    {
        $color = is_scalar($color) ? trim(strval($color)) : '';
        if (strlen($color) <= 0 || $color[0] != '#' || strlen($color) != 7) {
            $color = '#ffffff';
        }
        return $color;
    }
}
