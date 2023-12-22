<?php

class crmContactBanMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    protected $contact;
    protected $reason;

    public function execute()
    {
        $this->getData();
        $result = crmContactBlocker::ban($this->contact, $this->reason);
        $this->handleResult($result);
    }

    protected function getData()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $_json = $this->readBodyAsJson();
        $contact_id = ifempty($_json, 'id', null);
        $this->reason = ifempty($_json, 'reason', null);

        if (empty($contact_id)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'id'), 400);
        }
        if (!is_numeric($contact_id) || $contact_id < 1) {
            throw new waAPIException('invalid_param', _w('Invalid contact ID.'), 400);
        }

        $this->contact = $this->getContactModel()->getById($contact_id);
        if (empty($this->contact)) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        }
    }

    protected function handleResult($result)
    {
        if (!$result['result']) {
            throw new waAPIException($result['error'], $result['error_description'], 400);
        }
        $this->http_status_code = 204;
        $this->response = null;
    }
}
