<?php
/**
 * Changes main contact of an existing deal, possibly modifying contact data, too.
 */
class crmDealPersonUpdateController extends crmContactSaveController
{
    /**
     * @var crmDealModel
     */
    protected $dm;

    public function execute()
    {
        $data = $this->getData();
        $deal = $this->getDeal();
        $participant = waRequest::post('participant', null, waRequest::TYPE_ARRAY_TRIM);

        if (empty($data['id'])) {
            parent::execute();
        }

        $after = new waContact($this->getId());
        if (!$after->exists()) {
            throw new waException("Choose existing contact");
        }

        $before = $this->newContact($deal['contact_id']);

        if ($this->getId() && $deal['contact_id'] !== $this->getId()) {
            $this->getDealModel()->updateParticipant($deal['id'], $this->getId(), 'contact_id', ifset($participant['label']));

            $action_id ='deal_contact_change';
            $this->logAction($action_id, array('deal_id' => $deal['id']));
            $lm = new crmLogModel();
            $lm->log(
                $action_id,
                $deal['id'] * -1,
                $deal['id'],
                $before->getName(),
                $after->getName()
            );
        }
        $after['label'] = ifset($participant['label']);

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
        $view->assign(array(
            'contact' => $after,
            'is_registered' => !empty($after['password']),
            'type'    => 'contact_owner',
            'is_init_call' => $this->getCrmRights()->isInitCall(),
            'is_sms_configured' => $this->isSMSConfigured(),
            'can_manage_responsible' => $can_manage_responsible,
            'can_edit_deal'          => $can_edit_deal,
        ));

        $this->response = array(
            'html'    => $view->fetch(wa()->getAppPath('templates/actions/deal/DealContact.html', 'crm')),
            'contact' => array(
                'id' => $after->getId(),
                'name'     => $after->getName(),
                'photo_16' => $after->getPhoto(16),
                'label'    => $after['label'],
            ),
        );
    }

    public function getDeal()
    {
        $id = (int)$this->getRequest()->request('deal_id');
        if (!$id) {
            $this->notFound();
        }
        $deal = $this->getDealModel()->getById($id);
        if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }
        return $deal;
    }

    /**
     * @return crmDealModel
     * @throws waDbException
     * @throws waException
     */
    protected function getDealModel()
    {
        return $this->dm !== null ? $this->dm : ($this->dm = new crmDealModel());
    }

    /**
     * Create new waContact instance by contact ID, take into account possible not existing
     * @param int $contact_id
     * @return waContact
     * @throws waException
     */
    protected function newContact($contact_id)
    {
        $wa_contact = new waContact($contact_id);
        if (!$wa_contact->exists()) {
            $wa_contact = new waContact();
            $wa_contact['id'] = $contact_id;
            $wa_contact['name'] = sprintf_wp("Contact with ID %s doesn't exist", $contact_id);
        }
        return $wa_contact;
    }
}
