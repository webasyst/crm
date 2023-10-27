<?php

class crmContactUnpinMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $contact_id = ifempty($_json, 'id', 0);

        if (empty($contact_id)) {
            throw new waAPIException('required_param', 'Required parameter is missing: id', 400);
        } elseif (!is_numeric($contact_id)) {
            throw new waAPIException('invalid_param', 'Invalid contact ID', 400);
        } elseif ($contact_id < 1) {
            throw new waAPIException('not_found', 'Contact not found', 404);
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
            throw new waAPIException('error_db', $db_exception->getMessage(), 400);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }
}
