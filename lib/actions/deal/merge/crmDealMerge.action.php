<?php

class crmDealMergeAction extends crmBackendViewAction
{
    public function execute()
    {
        $ids = preg_split('~\s*,\s*~', waRequest::request('ids'));
        $iframe = waRequest::request('iframe', 0, waRequest::TYPE_INT);

        $allowed_ids = $this->dropUnallowed($ids);
        $dropped_ids_count = count($ids) - count($allowed_ids);

        $ids = $allowed_ids;

        $dm = new crmDealModel();

        $deals = $dm->select('*')->where("id IN('".join("','", $dm->escape($ids))."')")->fetchAll('id');
        $funnels = $this->getFunnels();

        $fsm = new crmFunnelStageModel();
        $stages = $fsm->select('*')->order('funnel_id, number')->fetchAll('id');
        crmHelper::getFunnelStageColors($funnels, $stages);

        $contact_ids = array();
        foreach ($deals as &$d) {
            $funnel = ifset($funnels[$d['funnel_id']], array());
            $stage = ifset($stages[$d['stage_id']], array());

            $d['funnel'] = $funnel;
            $d['stage'] = $stage;
            $d['reminder_state'] = crmHelper::getDealReminderState($d['reminder_datetime']);
            $d['reminder_title'] = crmHelper::getReminderTitle($d['reminder_state'], $d['reminder_datetime']);
            $contact_ids[$d['contact_id']] = 1;
            $contact_ids[$d['user_contact_id']] = 1;
        }
        unset($d);

        $contacts = $this->getContactsByIds(array_keys($contact_ids));

        if (!empty($iframe) && wa('crm')->whichUI('crm') !== '1.3') {
            $this->setLayout();
        }

        $this->view->assign(array(
            'iframe'   => $iframe,
            'deals'    => $deals,
            'contacts' => $contacts,
            'dropped_ids_count' => $dropped_ids_count
        ));
    }

    protected function getContactsByIds($ids)
    {
        if (!$ids) {
            return array();
        }
        $contacts = array();
        $collection = new waContactsCollection('/id/'.join(',', $ids)); // !!! check rights?..
        $col = $collection->getContacts(wa('crm')->getConfig()->getContactFields(), 0, count($ids));
        foreach ($col as $id => $c) {
            $contacts[$id] = new waContact($c);
        }
        return $contacts;
    }

    protected function getFunnels()
    {
        $fm = new crmFunnelModel();
        $funnels = $fm->getAllFunnels();
        if (!$funnels) {
            throw new crmNoFunnelsException();
        }
        return $funnels;
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
