<?php

class crmDealPinMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $deal_id = ifempty($_json, 'id', 0);

        if (empty($deal_id)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'id'), 400);
        }
        if (!is_numeric($deal_id)) {
            throw new waAPIException('invalid_param', _w('Invalid deal ID.'), 400);
        }
        if ($deal_id < 1) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        }

        $deal = $this->getDealModel()->getById((int) $deal_id);
        if (!$deal) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        }
        if (!$this->getCrmRights()->deal($deal)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        try {
            $result = $this->getRecentModel()->pin(-1 * $deal_id);
        } catch (Exception $_ex) {
            throw new waAPIException('error_db', $_ex->getMessage(), 500);
        }

        if (!$result) {
            throw new waAPIException('error_pin', _w('Deal pinning error.'), 500);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }
}
