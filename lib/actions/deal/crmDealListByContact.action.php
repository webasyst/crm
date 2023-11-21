<?php

/**
 * Deals of an existing contact. This shows contents of one of contact profile tabs.
 */
class crmDealListByContactAction extends crmDealListAction
{
    public function execute()
    {
        $contact_id = waRequest::get('id', null, waRequest::TYPE_INT);
        if (!$contact_id) {
            throw new waException(_w('Contact not found'));
        }
        $cm = new waContactModel();
        $employees = $cm->select('id')->where('company_contact_id='.(int)$contact_id)->fetchAll('id', true);
        $employees = array($contact_id => 1) + $employees;

        $dm = new crmDealModel();
        $deals = $dm->getList(array(
            'participants' => array_keys($employees),
            'check_rights' => true,
        ));

        $default_currency = wa()->getSetting('currency');

        foreach ($deals as &$d) {
            if ($d['user_contact_id']) {
                $d['user_contact'] = new waContact($d['user_contact_id']);
            }
            if ($d['contact_id']) {
                $d['contact'] = new waContact($d['contact_id']);
            }
            if ($d['amount'] && $d['currency_id'] && $d['currency_id'] != $default_currency) {
                $d['amount'] *= $d['currency_rate'];
                $d['currency_id'] = $default_currency;
            }
            $d['reminder_state'] = crmHelper::getDealReminderState($d['reminder_datetime']);
            $d['reminder_title'] = crmHelper::getReminderTitle($d['reminder_state'], $d['reminder_datetime']);
        }
        unset($d);

        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $funnels = $fsm->withStages($fm->getAllFunnels());

        $data = array(
            'deals'            => $deals,
            'funnels'          => $funnels,
            'contact_id'       => $contact_id,
            'available_funnel' => $fm->getAvailableFunnel(),
        );
        $this->view->assign($data);
    }
}
