<?php

class crmContactOperationDeleteAction extends crmContactOperationAction
{
    protected $contact_fields = '*';

    public function execute()
    {
        $contacts = $this->getContacts();

        if (empty($contacts)) {
            return $this->notFound();
        }

        $operation = new crmContactOperationDelete(array(
            'contacts' => $contacts
        ));

        $contacts = $operation->getContacts();
        $free_contacts = $operation->getFreeContacts();
        $linked_contacts = $operation->getLinkedContacts();

        $context = $this->getContext();
        $context['contact_ids'] = array_keys($contacts);

        $this->view->assign(array(
            'apps' => wa()->getApps(),
            'contacts' => $contacts,
            'context' => $context,
            'free_contacts' => $free_contacts,
            'linked_contacts' => $linked_contacts,
            'is_super_admin' => $operation->isSuperAdmin()
        ));
    }
}
