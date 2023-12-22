<?php

class crmSegmentExcludeMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $contact_ids = $this->readBodyAsJson();
        $segment_id  = $this->get('id', true);

        if (!is_numeric($segment_id)) {
            throw new waAPIException('invalid_param', _w('Invalid segment ID.'), 400);
        } elseif (
            $segment_id < 1
            || !$segment = $this->getSegmentModel()->getSegment($segment_id)
        ) {
            throw new waAPIException('not_found', _w('Segment not found.'), 404);
        } elseif (empty($segment['type']) || $segment['type'] !== crmSegmentModel::TYPE_CATEGORY) {
            throw new waAPIException('not_found', _w('Segment not found.'), 404);
        } elseif (!$this->getCrmRights()->canEditSegment($segment)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        if (!empty($contact_ids)) {
            $counters = $this->getSegmentModel()->deleteFrom($segment_id, $contact_ids);
        }

        $this->response = [
            'count' => (int) ifset($counters, 0, 'cnt', 0)
        ];
    }
}
