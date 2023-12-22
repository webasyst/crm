<?php

class crmDealDeleteMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_DELETE;

    public function execute()
    {
        $is_bulk_delete = true;
        $deal_ids = $this->get('id');
        if (empty($deal_ids)) {
            $_json = $this->readBodyAsJson();
            $deal_ids = ifset($_json, 'id', null);
        }
        if (empty($deal_ids)) {
            throw new waAPIException('invalid_request', _w('Deal identifier is required.'), 400);
        }

        if (!is_array($deal_ids)) {
            $deal_ids = [$deal_ids];
            $is_bulk_delete = false;
        }
        $deal_ids = array_map('intval', $deal_ids);
        $deal_ids = array_filter($deal_ids, function ($id) {
            return $id > 0;
        });
        $deal_ids = array_unique($deal_ids);

        if (empty($deal_ids)) {
            throw new waAPIException('invalid_request', _w('Invalid deal identifiers submitted.'), 400);
        }

        $allowed_to_delete = $this->getCrmRights()->dropUnallowedDeals($deal_ids, ['level' => crmRightConfig::RIGHT_DEAL_ALL]);
        if (empty($allowed_to_delete)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $this->getDealModel()->delete(
            $allowed_to_delete,
            ['reset' => ['message', 'conversation']]
        );

        if ($is_bulk_delete) {
            $this->http_status_code = 200;
            $this->response = [ 'deleted' => $allowed_to_delete ];
        } else {
            $this->http_status_code = 204;
            $this->response = null;
        }
    }
}
