<?php

class crmDealPinMethod extends crmApiAbstractMethod
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
        } elseif (
            $deal_id < 1
            || !$this->getDealModel()->getById((int) $deal_id)
        ) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        }

        try {
            $result = $this->getRecentModel()->insert([
                'user_contact_id' => $this->getUser()->getId(),
                'contact_id'      => - $deal_id,
                'is_pinned'       => 1,
                'view_datetime'   => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $_exception) {
            try {
                $result = $this->getRecentModel()->updateByField(
                    [
                        'user_contact_id' => $this->getUser()->getId(),
                        'contact_id'      => - $deal_id
                    ], [
                        'is_pinned' => 1,
                    ]
                );
            } catch (Exception $_ex) {
                throw new waAPIException('error_db', $_ex->getMessage(), 400);
            }
        }

        if (!$result) {
            throw new waAPIException('error_pin', 'Error pinned deal', 400);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }
}
