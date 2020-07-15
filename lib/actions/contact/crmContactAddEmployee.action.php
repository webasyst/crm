<?php

/**
 */
class crmContactAddEmployeeAction extends crmBackendViewAction
{
    public function execute()
    {
        $company_contact_id = waRequest::post('company_contact_id', null, waRequest::TYPE_STRING);

        // CONTACT
        $company_contact = new waContact($company_contact_id);
        if (!$this->getCrmRights()->contact($company_contact)) {
            $this->accessDenied();
        }

        $this->view->assign(array(
            'company_contact' => $company_contact,
        ));
    }
}
