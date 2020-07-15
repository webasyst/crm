<?php

class crmSettingsLostReasonsAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $dlm = new crmDealLostModel();

        $this->view->assign(array(
            'reasons'              => $dlm->select('*')->order('sort')->fetchAll('id'),
            'lost_reason_require'  => wa()->getSetting('lost_reason_require'),
            'lost_reason_freeform' => wa()->getSetting('lost_reason_freeform'),
        ));
    }
}