<?php

class crmSegmentArchiveMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    const ACTIONS = [
        'archive',
        'restore'
    ];

    public function execute()
    {
        $body_json = $this->readBodyAsJson();
        $segment_id = ifset($body_json, 'id', null);
        $acton = ifset($body_json, 'action', null);

        if (empty($segment_id)) {
            throw new waAPIException('empty_id', sprintf_wp('Missing required parameter: “%s”.', 'id'), 400);
        } elseif (!is_numeric($segment_id) || $segment_id < 1) {
            throw new waAPIException('invalid_id', sprintf_wp('Invalid “%s” value.', 'id'), 400);
        } elseif (empty($acton) || !in_array($acton, self::ACTIONS)) {
            throw new waAPIException('invalid_action', _w('Unknown action.'), 400);
        }

        $segment = $this->getSegmentModel()->getSegment($segment_id);
        if (!$segment || !$this->getCrmRights()->canArchiveSegment($segment)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $archived = ($acton === self::ACTIONS[0] ? 1 : 0);
        try {
            $this->getSegmentModel()->updateById($segment_id, ['archived' => $archived]);
        } catch (waDbException $dbe) {
            throw new waAPIException('error_db', $dbe->getMessage(), 400);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }
}
