<?php

class crmSegmentListMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    public function execute()
    {
        $segments = $this->filterData(
            $this->getSegments(),
            ['id', 'type', 'name', 'hash', 'sort', 'create_datetime', 'contact_id', 'shared', 'count', 'icon', 'category_id', 'archived', 'system_id', 'app_id', 'icon_path', 'is_editable'],
            ['id' => 'integer', 'contact_id' => 'integer', 'count' => 'integer', 'archived' => 'boolean', 'shared' => 'boolean', 'sort' => 'integer', 'category_id' => 'integer', 'create_datetime' => 'datetime']
        );
        array_multisort(array_column($segments, 'sort'), $segments);
        $this->response = array_reduce($segments, function($res, $el) {
            if ($el['shared']) {
                if ($el['archived']) {
                    $res['archived']['shared'][] = $el;
                } else {
                    $res['shared'][] = $el;
                }
            } else {
                if ($el['archived']) {
                    $res['archived']['my'][] = $el;
                } else {
                    $res['my'][] = $el;
                }
            }
            return $res;
        }, ['shared' => [], 'my' => [], 'archived' => [ 'shared' => [], 'my' => [] ]]);
    }

    protected function getSegments($filter = [])
    {
        $segments = $this->getSegmentModel()->getAllSegments($filter);
        foreach ($segments as &$segment) {
            $segment['is_editable'] = $this->getCrmRights()->canEditSegment($segment);
        }
        return $this->prepareSegmentIcons($segments);
    }
}