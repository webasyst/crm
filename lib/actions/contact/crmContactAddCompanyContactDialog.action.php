<?php

class crmContactAddCompanyContactDialogAction extends waViewAction
{
    public function execute()
    {
        $contact_id = waRequest::request('contact_id', 0, 'int');
        if (!$contact_id) {
            throw new waException('No contact_id', 404);
        }
        $contact = new waContact($contact_id);
        if (empty($contact) || !$contact->exists()) {
            throw new waException(_w('Contact not found'), 404);
        }
        if ($contact['is_company'] > 0) {
            throw new waException('This contact is a company', 403);
        }

        $rights = new crmRights();
        if (!$rights->contactEditable($contact)) {
            throw new waRightsException();
        }

        $this->view->assign(array(
            'contact'   => $contact,
        ));
    }
}
