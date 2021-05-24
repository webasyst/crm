<?php

/**
 * Dialog HTML to modify funnel of given deals
 */
class crmDealChangeFunnelAction extends crmBackendViewAction
{
    public function execute()
    {
        $ids = preg_split('~\s*,\s*~', waRequest::request('deal_ids'));

        $allowed_ids = $this->dropUnallowed($ids);

        $dropped_ids_count = count($ids) - count($allowed_ids);
        $ids = $allowed_ids;

        $dm = new crmDealModel();
        $deals = $dm->select('*')->where("id IN('".join("','", $dm->escape($ids))."')")->fetchAll('id');
        $d = reset($deals);
        $funnel_id = $d['funnel_id'];

        $shop_linked = 0;
        foreach ($deals as $d) {
            if ($d['external_id'] && strpos($d['external_id'], 'shop:') === 0) {
                $shop_linked++;
            }
        }

        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();

        $funnels = $fm->getAllFunnels();
        foreach ($funnels as &$f) {
            $f['stages'] = $fsm->getStagesByFunnel($f['id']);
        }
        unset($f);

        if (empty($funnels[$funnel_id])) {
            throw new waException('Funnel not found');
        }
        //$funnel = reset($funnels);

        $this->view->assign(array(
            'funnels'     => $funnels,
            'funnel'      => $funnels[$funnel_id],
            'deal_ids'    => $ids,
            'shop_linked' => $shop_linked,
            'dropped_ids_count' => $dropped_ids_count,
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
            'level' => crmRightConfig::RIGHT_DEAL_EDIT
        ]);
    }
}
