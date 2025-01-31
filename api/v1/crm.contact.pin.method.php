<?php

class crmContactPinMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $contact_id = ifempty($_json, 'id', null);

        if (empty($contact_id)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'id'), 400);
        }
        if (!is_numeric($contact_id) || $contact_id < 1) {
            throw new waAPIException('invalid_param', _w('Invalid contact ID.'), 400);
        }
        $contact_id = (int) $contact_id;
        $contact = new waContact($contact_id);
        if (!$contact->exists()) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        }
        $rights = $this->getCrmRights();
        if (!$rights->contact($contact)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        try {
            $result = $this->getRecentModel()->pin($contact_id);
        } catch (waDbException $db_ex) {
            throw new waAPIException('error_db', $db_exception->getMessage(), 500);
        }

        if (!$result) {
            throw new waAPIException('error_pin', _w('Contact pinning error.'), 500);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }
}
