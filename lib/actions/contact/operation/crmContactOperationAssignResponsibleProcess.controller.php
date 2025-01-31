<?php

class crmContactOperationAssignResponsibleProcessController extends crmContactOperationProcessController
{

    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm') && !wa()->getUser()->getRights('crm', 'edit')) {
            $this->accessDenied();
        }

        $responsible_id = waRequest::request('responsible_id', null, 'int');
        $rights = new crmRights(array(
            'contact' => $responsible_id,
        ));

        $contact_ids = explode(',', waRequest::request('contact_ids', '', 'string'));
        $contact_ids = array_filter($contact_ids, 'wa_is_int');
        // $contact_ids = $rights->dropUnallowedContacts($contact_ids, 'view');

        $contact_model = new crmContactModel();

        $result = 'ok';
        $message = null;
        $bad_users = null;
        foreach ($contact_ids as $contact_id) {
            $contact = new crmContact($contact_id);
            $isIncceptable = $contact->isResponsibleUserIncceptable($responsible_id);
            if (!$isIncceptable) {
                $contact_model->updateResponsibleContact($contact_id, $responsible_id);
            } elseif ($isIncceptable == 'no_adhoc_access') {
                $contact->addResponsibleToAdhock($responsible_id);
                $contact_model->updateResponsibleContact($contact_id, $responsible_id);
            } elseif ($isIncceptable == 'no_vault_access') {
                $result = 'no_vault_access';
                $bad_users[] = array('id' => $contact['id'], 'name' => htmlspecialchars($contact['name']), 'photo' => $contact->getPhoto(20));
                $message = _w(
                    'The responsible user has not been assigned to this client because of insufficient access rights.',
                    'The responsible user has not been assigned to these clients because of insufficient access rights.',
                    count($bad_users)
                );
            }
        }
        $this->response = array('result' => $result, 'message' => $message, 'users' => $bad_users);
    }
}
