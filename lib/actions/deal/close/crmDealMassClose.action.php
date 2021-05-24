<?php

class crmDealMassCloseAction extends crmBackendViewAction
{
    public function execute()
    {
        $ids = preg_split('~\s*,\s*~', waRequest::request('ids'));

        $allowed_ids = $this->dropUnallowed($ids);

        $dropped_ids_count = count($ids) - count($allowed_ids);
        $ids = $allowed_ids;

        $dm = new crmDealModel();
        $dlm = new crmDealLostModel();

        $deals = $dm->select('*')->where("id IN('".join("','", $dm->escape($ids))."')")->fetchAll('id');

        $shop_linked = 0;
        $funnel_id = 0;
        foreach ($deals as $d) {
            if ($d['external_id'] && strpos($d['external_id'], 'shop:') === 0) {
                $shop_linked++;
            }
            $funnel_id = $d['funnel_id'];
        }

        $this->view->assign(array(
            'deals'                => $deals,
            'reasons'              => $dlm->select('*')->where("funnel_id IN (0, {$funnel_id})")->order('sort')->fetchAll('id'),
            'lost_reason_require'  => wa()->getSetting('lost_reason_require'),
            'lost_reason_freeform' => wa()->getSetting('lost_reason_freeform'),
            'shop_linked'          => $shop_linked,
            'dropped_ids_count'    => $dropped_ids_count
        ));
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
