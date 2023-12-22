<?php

class crmContactUnpinMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $contact_id = ifempty($_json, 'id', 0);

        if (empty($contact_id)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'id'), 400);
        } elseif (!is_numeric($contact_id)) {
            throw new waAPIException('invalid_param', _w('Invalid contact ID.'), 400);
        } elseif ($contact_id < 1) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        }

        try {
            $this->getRecentModel()->updateByField(
                [
                    'user_contact_id' => $this->getUser()->getId(),
                    'contact_id'      => $contact_id
                ], [
                    'is_pinned' => 0,
                ]
            );
        } catch (waDbException $db_exception) {
            throw new waAPIException('error_db', $db_exception->getMessage(), 500);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }
}
