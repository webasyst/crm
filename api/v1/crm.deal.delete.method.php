<?php

class crmDealDeleteMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_DELETE;

    public function execute()
    {
        $deal_id = (int) $this->get('id', true);

        if (empty($deal_id) || $deal_id < 0) {
            throw new waAPIException('not_found', 'Deal not found', 404);
        } elseif (!$this->getCrmRights()->dropUnallowedDeals([$deal_id], ['level' => crmRightConfig::RIGHT_DEAL_ALL])) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $this->getDealModel()->delete(
            [$deal_id],
            ['reset' => ['message', 'conversation']]
        );
        $this->http_status_code = 204;
        $this->response = null;
    }
}
