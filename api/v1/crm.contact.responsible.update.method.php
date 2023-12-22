<?php

class crmContactResponsibleUpdateMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $user_id = (int) ifempty($_json, 'user_id', 0);
        $contact_id = (int) $this->get('id', true);

        if ($contact_id < 1) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        } elseif ($user_id < 0) {
            throw new waAPIException('not_found', _w('User not found.'), 404);
        }

        $contact = new crmContact($contact_id);
        if (!$contact->exists()) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        } elseif (!$this->getCrmRights()->contactEditable($contact)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        if ($user_id !== 0 && !(new crmContact($user_id))->exists()) {
            throw new waAPIException('not_found', _w('User not found.'), 404);
        }

        $contact_model = $this->getContactModel();
        $is_incceptable = $contact->isResponsibleUserIncceptable($user_id);
        if (!$is_incceptable) {
            $contact_model->updateResponsibleContact($contact_id, $user_id);
        } elseif ($is_incceptable == 'no_adhoc_access') {
            $contact->addResponsibleToAdhock($user_id);
            $contact_model->updateResponsibleContact($contact_id, $user_id);
        } elseif ($is_incceptable == 'no_vault_access') {
            throw new waAPIException('no_vault_access', _w('Change the access to the contact.'), 400);
        }
    }
}
