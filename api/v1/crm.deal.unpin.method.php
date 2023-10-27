<?php

class crmDealUnpinMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $deal_id = ifempty($_json, 'id', 0);

        if (empty($deal_id)) {
            throw new waAPIException('required_param', 'Required parameter is missing: id', 400);
        } elseif (!is_numeric($deal_id)) {
            throw new waAPIException('invalid_param', 'Invalid deal ID', 400);
        } elseif ($deal_id < 1) {
            throw new waAPIException('not_found', 'Deal not found', 404);
        }

        try {
            $this->getRecentModel()->updateByField(
                [
                    'user_contact_id' => $this->getUser()->getId(),
                    'contact_id'      => - $deal_id
                ], [
                    'is_pinned' => 0,
                ]
            );
        } catch (Exception $_exception) {
            throw new waAPIException('error_db', $_exception->getMessage(), 400);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }
}