<?php
/**
 * Assign an existing deal to another backend user. Inline editor on DealId page.
 */
class crmDealChangeUserController extends crmJsonController
{
    public function execute()
    {
        $user_contact_id = waRequest::post('user_contact_id', null, waRequest::TYPE_INT);

        $deal = $this->getDeal();

        if (!$this->validate($user_contact_id, $deal)) {
            return false;
        }

        $before_user = new waContact($deal['user_contact_id']);
        $after_user = new waContact($user_contact_id);

        if ($deal['user_contact_id'] !== $user_contact_id) {

            $this->getDealModel()->updateParticipant($deal['id'], $user_contact_id, 'user_contact_id');

            $action_id = 'deal_transfer';
            $this->logAction($action_id, array('deal_id' => $deal['id']));
            $lm = new crmLogModel();

            $before_user_name = "";
            if ($deal['user_contact_id'] > 0) {
                $before_user_name = "deleted contact_id={$deal['user_contact_id']}";
                if ($before_user->exists()) {
                    $before_user_name = $before_user->getName();
                }
            }

            $after_user_name = $after_user->getName();

            $lm->log(
                $action_id,
                $deal['id'] * -1,
                $deal['id'],
                $before_user_name,
                $after_user_name,
                null,
                ['user_id_before' => $before_user->getId(), 'user_id_after' => $after_user->getId()]
            );
            $deal['user_contact_id'] = $user_contact_id;

            // Update crm_conversation.user_contact_id in related not closed conversations
            $this->getConversationModel()->updateByField(array('deal_id' => $deal['id'], 'is_closed' => 0), array('user_contact_id' => $user_contact_id));
        }

        // Funnel and stages
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $funnel = $fsm->withStages(array($deal['funnel_id'] => $fm->getById($deal['funnel_id'])));
        $funnel = reset($funnel);

        $rights = new crmRights();
        $funnel_rights_value = $rights->funnel($funnel);

        $can_manage_responsible = $deal['user_contact_id'] == wa()->getUser()->getId() || $funnel_rights_value > 2 ||
            (!$deal['user_contact_id'] && $funnel_rights_value > 0);

        $deal_access_level = $this->getCrmRights()->deal($deal);
        $can_edit_deal = $deal_access_level > crmRightConfig::RIGHT_DEAL_VIEW;

        $view = wa()->getView();
        $view->assign(array(
            'contact'                => $after_user,
            'is_registered'          => !empty($after_user['password']),
            'type'                   => 'owner',
            'is_sms_configured'      => $this->isSMSConfigured(),
            'can_manage_responsible' => $can_manage_responsible,
            'can_edit_deal'          => $can_edit_deal,
        ));

        $deal_access_denied = $deal_access_level <= crmRightConfig::RIGHT_DEAL_NONE;

        $this->response = array(
            'deal' => $deal_access_denied ? null : $deal,
            'deal_access_denied' => $deal_access_denied,
            'html' => $view->fetch(wa()->getAppPath('templates/actions/deal/DealContact.html', 'crm')),
        );
    }

    protected function validate($user_contact_id, $deal)
    {
        if (!$user_contact_id) {
            throw new waException('Empty contact');
        }

        $user = new waContact($user_contact_id);
        if (!$user->exists()) {
            throw new waException('Empty contact');
        }

        $rights1 = new crmRights();
        $rights2 = new crmRights(array('contact' => $user_contact_id));
        $r1_funnel_value = $rights1->funnel($deal['funnel_id']);
        if (!$rights2->funnel($deal['funnel_id']) ||
            ($r1_funnel_value < crmRightConfig::RIGHT_FUNNEL_ALL && $deal['user_contact_id'] != wa()->getUser()->getId() && ($deal['user_contact_id'] || $r1_funnel_value < crmRightConfig::RIGHT_FUNNEL_OWN))
        ) {
            throw new waRightsException();
        }

        return true;
    }

    public function getDeal()
    {
        $id = (int)$this->getRequest()->request('id');
        if (!$id) {
            $this->notFound();
        }
        $deal = $this->getDealModel()->getById($id);
        if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }
        return $deal;
    }
}
