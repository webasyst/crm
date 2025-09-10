<?php

class crmCallAssociateDealDialogAction extends crmBackendViewAction
{
    public function execute()
    {
        $call_id = waRequest::get("call_id", 0, waRequest::TYPE_INT);

        $call_model = new crmCallModel();
        $call = $call_model->getById($call_id);

        if (!$call) {
            throw new waException(_w('Call not found'), 404);
        }

        if (empty($call['client_contact_id'])) {
            throw new waException(_w('A call without a client cannot be linked to a deal.'), 404);
        }

        $contact = new crmContact($call['client_contact_id']);

        if (empty($contact) || !$contact->exists()) {
            throw new waException(_w('Contact not found'), 404);
        }

        $dm = new crmDealModel();
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();

        $funnel = $fm->getAvailableFunnel();
        if (!$funnel) {
            throw new waRightsException();
        }
        $stage_id = $fsm->select('id')->where(
            'funnel_id = ' . (int)$funnel['id']
        )->order('number')->limit(1)->fetchField('id');

        // Just empty deal, for new message
        $now = date('Y-m-d H:i:s');
        $new_deal = $dm->getEmptyDeal();
        $new_deal = array_merge($new_deal, array(
            'creator_contact_id' => wa()->getUser()->getId(),
            'create_datetime' => $now,
            'update_datetime' => $now,
            'funnel_id' => $funnel['id'],
            'stage_id' => $stage_id,
        ));

        $funnels = $fsm->withStages($fm->getAllFunnels(true));
        if (empty($funnels[$new_deal['funnel_id']])) {
            throw new waException('Funnel not found');
        }
        $stages = $fsm->getStagesByFunnel($funnels[$new_deal['funnel_id']]);

        $this->view->assign(array(
            'new_deal'      => $new_deal,
            'stages'        => $stages,
            'funnels'       => $funnels,
            'contact'       => $contact,
            'contact_deals' => $this->getContactDeals($contact['id']),
            'call'          => $call,
        ));
    }

    protected function getContactDeals($contact_id)
    {
        if (!$contact_id) {
            throw new waException(_w('Contact not found'), 404);
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
            $d['name'] = htmlspecialchars($d['name']);
            $d['reminder_state'] = crmHelper::getDealReminderState($d['reminder_datetime']);
            $d['reminder_title'] = crmHelper::getReminderTitle($d['reminder_state'], $d['reminder_datetime']);
        }
        unset($d);

        return $deals;
    }
}
