<?php
/**
 * Adds another backend user to an existing deal.
 * Inline editor on DealId page.
 */
class crmDealUserAddController extends crmJsonController
{
    public function execute()
    {
        $deal_id = $this->getDealId();
        $contact_id = waRequest::post('contact_id', null, waRequest::TYPE_INT);

        $dm = new crmDealModel();
        $dpm = new crmDealParticipantsModel();

        $deal = $this->getDeal();

        // Check access rights
        $deal_access_level = $this->getCrmRights()->deal($deal);
        if ($deal_access_level <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }

        $rights = new crmRights(array('contact' => $contact_id));
        if (!$rights->funnel($deal['funnel_id'])) {
            $this->errors = _w('Access denied');
            return;
        }
        if ($dm->isRelated($deal_id, $contact_id, crmDealParticipantsModel::ROLE_USER)) {
            $this->errors[] = array(
                "name" => "contact",
                "text" => _w('Participant already exists')
            );
            return;
        }
        $dpm->insert(array(
            'contact_id' => $contact_id,
            'deal_id'    => $deal_id,
            'role'       => crmDealParticipantsModel::ROLE_USER,
        ));
        $c = new waContact($contact_id);

        $action = 'deal_addcontact';
        $this->logAction($action, array('deal_id' => $deal_id), $contact_id);
        $lm = new crmLogModel();
        $lm->log(
            $action,
            $deal_id * -1,
            $deal_id,
            null,
            $c->getName(),
            null,
            ['contact_id' => $contact_id]
        );
        
        $can_edit_deal = $deal_access_level > crmRightConfig::RIGHT_DEAL_VIEW;

        $view = wa()->getView();
        $view->assign(array(
            'contact'       => $c,
            'is_registered' => !empty($c['password']),
            'is_sms_configured' => $this->isSMSConfigured(),
            'can_edit_deal' => $can_edit_deal,
            'role_id' => 'USER'
        ));
        $this->response = array(
            'html' => $view->fetch(wa()->getAppPath('templates/actions/deal/DealContact.html', 'crm')),
        );
    }

    protected function getDealId()
    {
        return waRequest::post('deal_id', null, waRequest::TYPE_INT);
    }

    protected function getDeal()
    {
        $id = $this->getDealId();
        if ($id <= 0) {
            $this->notFound();
        }
        $deal = $this->getDealModel()->getDeal($id, true, true);
        if (!$deal) {
            $this->notFound();
        }
        return $deal;
    }
}
