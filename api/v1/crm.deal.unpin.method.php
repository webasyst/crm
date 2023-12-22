<?php

class crmDealUnpinMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $deal_id = ifempty($_json, 'id', 0);

        if (empty($deal_id)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'id'), 400);
        } elseif (!is_numeric($deal_id)) {
            throw new waAPIException('invalid_param', _w('Invalid deal ID.'), 400);
        } elseif ($deal_id < 1) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
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
            throw new waAPIException('error_db', $_exception->getMessage(), 500);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }
}
