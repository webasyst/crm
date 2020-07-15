<?php

/**
 */
class crmContactAddEmployeeSaveController extends crmContactSaveController
{
    public function execute()
    {
        // $contact_action = waRequest::post('contact_action', null, waRequest::TYPE_STRING);
        $contact = waRequest::post('contact', array(), waRequest::TYPE_ARRAY_TRIM);

        $company_contact_id = ifset($contact['company_contact_id']);

        if (!$this->getCrmRights()->contact($company_contact_id)) {
            $this->accessDenied();
        }
        parent::execute();
    }

    protected function getData()
    {
        $data = (array)$this->getParameter('contact');

        if ($this->getId() > 0) {
            $c = new waContact($data['id']);
            $data['name'] = $c->getName();
            $data['firstname'] = $c->get('firstname');
            $data['lastname'] = $c->get('lastname');
            $data['middlename'] = $c->get('middlename');
        }
        if ($this->getId() <= 0) {
            $data['create_method'] = 'add';
            $data['crm_user_id'] = $this->autoResponsible();
        }
        return $data;
    }
}
