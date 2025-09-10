<?php

class crmMessageConversationAssociateDealDialogAction extends crmBackendViewAction
{
    public function execute()
    {
        $conversation_id = waRequest::get("conversation_id", 0, waRequest::TYPE_INT);

        $conversation = $this->getConversationModel()->getById($conversation_id);

        if (empty($conversation)) {
            $this->notFound(_w('Conversation not found'));
        }

        if (empty($conversation['contact_id'])) {
            $this->notFound(_w('Conversation contact not found.'));
        }

        $contact = new crmContact($conversation['contact_id']);

        if (empty($contact) || !$contact->exists()) {
            $this->notFound(_w('Conversation contact not found.'));
        }

        if (!$this->getCrmRights()->canEditConversation($conversation)) {
            $this->accessDenied();
        }

        $dm = new crmDealModel();
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();

        $funnel = $fm->getAvailableFunnel();
        if (empty($funnel)) {
            $this->accessDenied();
        }
        $stage_id = $fsm->select('id')->where(
            'funnel_id = '.(int)$funnel['id']
        )->order('number')->limit(1)->fetchField('id');

        // Just empty deal, for new message
        $now = date('Y-m-d H:i:s');
        $new_deal = $dm->getEmptyDeal();
        $new_deal = array_merge($new_deal, array(
            'creator_contact_id' => wa()->getUser()->getId(),
            'create_datetime'    => $now,
            'update_datetime'    => $now,
            'funnel_id'          => $funnel['id'],
            'stage_id'           => $stage_id,
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
            'conversation'  => $conversation,
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
