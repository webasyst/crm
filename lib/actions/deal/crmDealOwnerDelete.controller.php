<?php
/**
 * Remove a owner from a deal. Inline editor on DealId page.
 */
class crmDealOwnerDeleteController extends crmJsonController
{
    protected $table = 'crm_deal';

    public function execute()
    {
        $deal_id = waRequest::post('deal_id', null, waRequest::TYPE_INT);
        $owner_id = waRequest::post('owner_id', null, waRequest::TYPE_INT);

        $dm = new crmDealModel();
        $dpm = new crmDealParticipantsModel();

        $deal = $dm->getById($deal_id);
        if (!$deal) {
            throw new waException(_w('Deal not found'));
        }

        if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }
        $rights = new crmRights();
        if ($rights->funnel($deal['funnel_id']) < 3 && $deal['user_contact_id'] != wa()->getUser()->getId()) {
            throw new waRightsException();
        }

        $dm->updateById($deal_id, array('user_contact_id' => 0));            // removing from crm_deal.user_contact_id
        $dpm->deleteByField(array('contact_id' => $owner_id, 'deal_id' => $deal_id, 'role_id' => 'USER'));         // removing from crm_deal_participants

        $c = new waContact($owner_id);

        $action = 'deal_removeowner';
        $this->logAction($action, array('deal_id' => $deal_id), $owner_id);
        $lm = new crmLogModel();
        $lm->log(
            $action,
            $deal_id * -1,
            $deal_id,
            $c->getName(),
            null,
            null,
            ['contact_id' => $owner_id]
        );
    }
}
