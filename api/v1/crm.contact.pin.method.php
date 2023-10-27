<?php

class crmContactPinMethod extends crmApiAbstractMethod
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
        } elseif (
            $contact_id < 1
            || !$this->getContactModel()->getById((int) $contact_id)
        ) {
            throw new waAPIException('not_found', 'Contact not found', 404);
        }

        try {
            $result = $this->getRecentModel()->insert([
                'user_contact_id' => $this->getUser()->getId(),
                'contact_id'      => $contact_id,
                'is_pinned'       => 1,
                'view_datetime'   => date('Y-m-d H:i:s')
            ]);
        } catch (waDbException $db_exception) {
            try {
                $result = $this->getRecentModel()->updateByField(
                    [
                        'user_contact_id' => $this->getUser()->getId(),
                        'contact_id'      => $contact_id
                    ], [
                        'is_pinned' => 1,
                    ]
                );
            } catch (waDbException $db_ex) {
                throw new waAPIException('error_db', $db_exception->getMessage(), 400);
            }
        }

        if (!$result) {
            throw new waAPIException('error_pin', 'Error pinned contact', 400);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }
}
