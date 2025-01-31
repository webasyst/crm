<?php
/**
 * Saves existing contact from DealChangeClient dialog
 * without switching to another client.
 */
class crmDealContactUpdateController extends crmContactSaveController
{
    public function execute()
    {
        try {
            parent::execute();
            $ok = true;
        } catch (waException $e) {
            $ok = false;
        }
        $id = (int)$this->getRequest()->request('deal_id');
        if (!$id) {
            throw new waException(_w('Deal not found'));
        }
        $deal = $this->getDealModel()->getById($id);
        if ($this->getCrmRights()->deal($deal, true) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }
        $contact = waRequest::post('contact', null, waRequest::TYPE_ARRAY_TRIM);
        $participant = waRequest::post('participant', null, waRequest::TYPE_ARRAY_TRIM);

        if ($ok) {
            $this->getDealModel()->updateParticipant($deal['id'], $contact['id'], 'contact_id', ifset($participant['label']));
        } elseif ($participant['label']) {
            $dpm = new crmDealParticipantsModel();
            $client = $dpm->getByField(array('deal_id' => $deal['id'], 'contact_id' => $contact['id'], 'role_id' => 'CLIENT'));
            if ($client) {
                $dpm->updateByField(
                    array('deal_id' => $deal['id'], 'contact_id' => $contact['id'], 'role_id' => 'CLIENT'),
                    array('label' => $participant['label'])
                );
            }
        }

        // Funnel and stages
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $funnel = $fsm->withStages(array($deal['funnel_id'] => $fm->getById($deal['funnel_id'])));
        $funnel = reset($funnel);

        $rights = new crmRights();
        $funnel_rights_value = $rights->funnel($funnel);

        $can_manage_responsible = $deal['user_contact_id'] == wa()->getUser()->getId() || $funnel_rights_value > 2 ||
            (!$deal['contacts']['user'] && $funnel_rights_value > 0);

        $deal_access_level = $this->getCrmRights()->deal($deal);
        if ($deal_access_level <= crmRightConfig::RIGHT_DEAL_NONE) {
            $this->accessDenied();
        }

        $can_edit_deal = $deal_access_level > crmRightConfig::RIGHT_DEAL_VIEW;

        $view = wa()->getView();
        $c = new waContact($contact['id']);
        $c['label'] = ifset($participant['label']);

        $view->assign(array(
            'contact'      => $c,
            'is_registered' => !empty($c['password']),
            'type'         => 'contact_owner',
            'is_init_call' => $this->getCrmRights()->isInitCall(),
            'is_sms_configured' => $this->isSMSConfigured(),
            'can_manage_responsible' => $can_manage_responsible,
            'can_edit_deal'          => $can_edit_deal,
        ));

        $this->response = array(
            'html'    => $view->fetch(wa()->getAppPath('templates/actions/deal/DealContact.html', 'crm')),
            'contact' => array(
                'name'     => $c->getName(),
                'photo_16' => $c->getPhoto(16),
            ),
        );
    }
}
