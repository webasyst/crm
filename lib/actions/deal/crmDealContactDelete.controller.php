<?php
/**
 * Excludes a backend user from a deal. Inline editor on DealId page.
 */
class crmDealContactDeleteController extends crmJsonController
{
    public function execute()
    {
        $deal_id = waRequest::post('deal_id', null, waRequest::TYPE_INT);
        $contact_id = waRequest::post('contact_id', null, waRequest::TYPE_INT);
        $role_id = waRequest::post('role_id', null, waRequest::TYPE_STRING_TRIM);

        if ($deal_id <= 0 || $contact_id <= 0) {
            return;
        }

        $participant_params = array(
            'contact_id' => $contact_id,
            'deal_id' => $deal_id
        );
        if ($role_id) {
            $participant_params['role_id'] = $role_id;
        }

        $dpm = new crmDealParticipantsModel();
        $participant = $dpm->getByField($participant_params);
        if (!$participant) {
            return; // no need to throw exception and no need to go further
        }

        $dm = new crmDealModel();

        $deal = $dm->getById($deal_id);
        if (!$deal) {
            throw new waException(_w('Deal not found'));
        }

        if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }

        $rights = new crmRights();
        $funnel_rights_value = $rights->funnel($deal['funnel_id']);

        if ($contact_id == $deal['user_contact_id']) { // responsible
            if ($deal['user_contact_id'] != wa()->getUser()->getId() && $funnel_rights_value < 3 &&
                ($deal['user_contact_id'] || $funnel_rights_value < 1)) {
                throw new waRightsException();
            }
        } else {
            if ($funnel_rights_value < 1) {
                throw new waRightsException();
            }
        }

        $dpm->deleteByField($participant_params);
        if ($contact_id == $deal['user_contact_id']) {
            $dm->updateById($deal_id, array('user_contact_id' => 0));
        }

        $c = new waContact($contact_id);

        $contact_name = 'deleted contact_id=' . $contact_id;
        if ($c->exists()) {
            $contact_name = $c->getName();
        }

        $action = 'deal_removecontact';
        $this->logAction($action, array('deal_id' => $deal_id), $contact_id);
        $lm = new crmLogModel();
        $lm->log(
            $action,
            $deal_id * -1,
            $deal_id,
            $contact_name,
            null,
            null,
            ['contact_id' => $contact_id]
        );
    }
}
