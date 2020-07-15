<?php

class crmContactOperationDeleteProcessController extends crmContactOperationProcessController
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

        $result = $operation->execute();
        if (!$result) {
            $this->accessDenied();
        }

        $this->logAction('contact_delete', $result['log_params']);

        $this->response(array(
            'deleted' => $result['count'],
            'message' => sprintf(_w("%d contact has been deleted", "%d contacts have been deleted", $result['count']), $result['count'])
        ));
    }
}
