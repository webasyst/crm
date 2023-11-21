<?php

/**
 * Page to edit an existing deal.
 */
class crmDealEditAction extends crmBackendViewAction
{

    public function execute()
    {
        $deal_id = waRequest::param('id', null, waRequest::TYPE_INT);
        $contact_id = waRequest::request('contact', null, waRequest::TYPE_INT);

        $dm  = new crmDealModel();
        $fm  = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $cm  = new crmContactModel();

        $contact = null;

        if ($deal_id) {
            $deal = $dm->getDeal($deal_id, false, true);
            if (!$deal) {
                throw new waException(_w('Deal not found'));
            }
            if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
                $this->accessDenied();
            }
        } else {
            $funnel = $fm->getAvailableFunnel();
            if (!$funnel) {
                throw new waRightsException();
            }
            $stage_id = $fsm->select('id')->where(
                'funnel_id = '.(int)$funnel['id']
            )->order('number')->limit(1)->fetchField('id');

            $name = null;
            if ($contact_id) {
                $contact = new waContact($contact_id);
                $name = sprintf_wp('%s deal', $contact->getName());
            }
            $now = date('Y-m-d H:i:s');
            $deal = $dm->getEmptyDeal($funnel['id']);
            $deal = array_merge($deal, array(
                'name'               => $name,
                'creator_contact_id' => wa()->getUser()->getId(),
                'create_datetime'    => $now,
                'update_datetime'    => $now,
                'funnel_id'          => $funnel['id'],
                'stage_id'           => $stage_id,
                'contact_id'         => $contact_id,
                'contact'            => $contact,
            ));
        }
        $deal['shop_order_number'] = crmShop::getEncodedOrderId($deal);

        /**/
        $funnels = $fm->getAllFunnels();
        if (empty($funnels[$deal['funnel_id']])) {
            $funnel = reset($funnels);
            $stages = $fsm->getStagesByFunnel($funnel);
        } else {
            $stages = $fsm->getStagesByFunnel($funnels[$deal['funnel_id']]);
        }
        $owners = $cm->getAllContactsForDeal($deal); // возможные контакты с правом доступа к сделке/воронке
        /**/

        // Participant
        if ($deal_id) {
            $deal_contact = new waContact($deal['contact_id']);
            if ($deal_contact->exists()) {
                $deal_contact['label'] = crmDeal::getRoleLabel($deal);
                $contact = $deal_contact;
            }
        }

        $deal = $this->prepareFields($deal);

        if (!empty($deal['user_contact_id']) && $deal['user_contact_id'] != wa()->getUser()->getId()) {
            $assign_to_user = new waContact($deal['user_contact_id']);
        } else {
            $assign_to_user = wa()->getUser();
        }

        $this->view->assign(array(
            'deal'             => $deal,
            'stages'           => $stages,
            'funnels'          => $funnels,
            'owners'           => $owners,
            'currencies'       => $this->getCurrencies(),
            'currency'         => $this->getConfig()->getCurrency(),
            'contact'          => $contact,
            'assign_to_user'   => $assign_to_user,
            'can_edit_contact' => $this->getCrmRights()->contactEditable($deal['contact_id'])
        ));
    }

    protected function getCurrencies()
    {
        return $this->getCurrencyModel()->getAll();
    }

    protected function prepareFields($deal)
    {
        if (empty($deal['fields'])) {
            return $deal;
        }

        unset($deal['fields']['source']);

        foreach ($deal['fields'] as $field_id => &$info) {
            $field = crmDealFields::get($field_id);
            $funnel_parameters = isset($info['funnels_parameters']) ? $info['funnels_parameters'] : null;
            $renderer = new crmFormFieldRenderer($field, array(
                'value' => $info['value'],
                'funnel_parameters' => $funnel_parameters,
                'namespace' => 'deal[params]',
                'template' => $field->getType() === 'Date' ? 'date.datepicker.html' : null,
                'role' => 'constructor',
            ));
            $info['html'] = $renderer->render();
        }
        unset($info);

        return $deal;
    }
}
