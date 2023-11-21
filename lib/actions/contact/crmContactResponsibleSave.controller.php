<?php
class crmContactResponsibleSaveController extends crmJsonController
{
    public function execute()
    {
        $contact_id = waRequest::request('contact_id', null, 'int');
        $responsible_id = waRequest::request('responsible_id', null, 'int');

        $contact = new crmContact($contact_id);
        $contact_model = new crmContactModel();

        $rights = new crmRights();
        if (!$rights->contactEditable($contact)) {
            $this->accessDenied();
        }

        if (empty($contact) || !$contact->exists()) {
            throw new waException(_w('Contact not found'), 404);
        } else {
            $this->response = array('result' => 'ok');
            $isIncceptable = $contact->isResponsibleUserIncceptable($responsible_id);
            if (!$isIncceptable) {
                $contact_model->updateResponsibleContact($contact_id, $responsible_id);
            } elseif ($isIncceptable == 'no_adhoc_access') {
                $contact->addResponsibleToAdhock($responsible_id);
                $contact_model->updateResponsibleContact($contact_id, $responsible_id);
            } elseif ($isIncceptable == 'no_vault_access') {
                $this->response = array('result' => $isIncceptable, 'message' => _w('Change the access to the contact.'));
            }
        }
    }

}
