<?php

class crmContactOperationAssignResponsibleAction extends crmContactOperationAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm') && !wa()->getUser()->getRights('crm', 'edit')) {
            $this->accessDenied();
        }

        $this->view->assign(array(
            'checked_count' => $this->getCheckedCount(),
            'contact_ids'   => $this->getContactIds()
        ));
    }

    public function getContactIds()
    {
        $contact_ids = array();
        foreach ($this->getContacts() as $id => $param) {
            array_push($contact_ids, $id);
        }
        return implode(",", $contact_ids);
    }
}
