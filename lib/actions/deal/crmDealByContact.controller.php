<?php

/**
 * Deals of an existing contact. This shows in new email dialog (../crm/message/).
 */
class crmDealByContactController extends crmJsonController
{
    const STATUS_ALL = 'all';

    public function execute()
    {
        $status_id = waRequest::get('select', crmDealModel::STATUS_OPEN, waRequest::TYPE_STRING_TRIM);
        $only_existing_stage = (bool)waRequest::get('only_existing_stage', null, waRequest::TYPE_INT);
        $contact_id = waRequest::get('id', null, waRequest::TYPE_INT);
        if (!$contact_id) {
            $this->response = array(
                'contact_id' =>array(),
                'deals' => array(),

            );
            return;
        }
        $cm = new waContactModel();
        $employees = $cm->select('id')->where('company_contact_id='.(int)$contact_id)->fetchAll('id', true);
        $employees = array($contact_id => 1) + $employees;

        $params = array(
            'participants' => array_keys($employees),
            'check_rights' => true,
        );

        $available_status = array(crmDealModel::STATUS_OPEN, crmDealModel::STATUS_LOST, crmDealModel::STATUS_WON);
        if ($status_id !== self::STATUS_ALL && in_array($status_id, $available_status)) {
            $params['status_id'] = $status_id;
        }

        $deals = $this->getDealModel()->getList($params);

        $default_currency = wa()->getSetting('currency');

        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $funnels = $fsm->withStages($fm->getAllFunnels(true));

        foreach ($deals as $i => &$d) {
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

            if (
                $only_existing_stage &&
                ( !$d['funnel_id'] || !$d['stage_id'] || !isset($funnels[$d['funnel_id']]) || !isset($funnels[$d['funnel_id']]['stages'][$d['stage_id']]) )
            ) {
                unset($deals[$i]);
            }
        }
        $this->response = array(
            'contact_id'       => $contact_id,
            'deals'            => $deals,
            'funnels'          => $funnels,
            'available_funnel' => $fm->getAvailableFunnel(),
        );
    }
}
