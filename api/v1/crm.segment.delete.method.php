<?php

class crmSegmentDeleteMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_DELETE;

    public function execute()
    {
        $segment_id = (int) $this->get('id', true);

        $segment = $this->getSegmentModel()->getSegment($segment_id);
        if (!$this->getCrmRights()->canEditSegment($segment)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        try {
            $this->getSegmentModel()->deleteById($segment_id);
        } catch (waDbException $dbe) {
            throw new waAPIException('error_db', $dbe->getMessage(), 400);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }
}
