<?php

class crmDealReopenMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $deal_id = (int) $this->get('id', true);

        if ($deal_id < 1) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        }

        $deal_model = $this->getDealModel();
        $deal = $deal_model->getById($deal_id);
        if (!$deal) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        } elseif ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        } elseif (!in_array($deal['status_id'], ['WON', 'LOST'])) {
            throw new waAPIException('warning', _w('The deal is not closed.'), 400);
        }

        $deal_model->updateById($deal_id, [
            'status_id'       => 'OPEN',
            'update_datetime' => date('Y-m-d H:i:s'),
            'closed_datetime' => null
        ]);
        $this->getLogModel()->log('deal_reopen', $deal_id * -1, $deal_id);

        $this->http_status_code = 204;
        $this->response = null;
    }
}
