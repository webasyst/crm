<?php

class crmSegmentUpdateMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_PUT;

    public function execute()
    {
        $segment_id = $this->get('id', true);
        $body_json  = $this->readBodyAsJson();
        $name   = trim(ifset($body_json, 'name', ''));
        $hash   = trim(ifset($body_json, 'hash', ''));
        $icon   = trim(ifset($body_json, 'icon', ''));
        $shared = (int) ifset($body_json, 'shared', 0);
        $shared = ($shared === 0 ? 0 : 1);

        if (!is_numeric($segment_id)) {
            throw new waAPIException('invalid_param', _w('Invalid segment ID.'), 400);
        } elseif (
            $segment_id < 1
            || !$segment = $this->getSegmentModel()->getSegment($segment_id)
        ) {
            throw new waAPIException('not_found', _w('Segment not found.'), 404);
        } elseif (!$this->getCrmRights()->canEditSegment($segment)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }
        if (!in_array($icon, crmSegmentModel::getIcons('2.0'))) {
            $s = $this->prepareSegmentIcons([$segment]);
            $icon = reset($s)['icon'];
        }

        $data = array_filter([
            'name' => $name,
            'icon' => $icon,
            'hash' => $hash
        ]) + ['shared' => $shared];

        $this->getSegmentModel()->update($segment_id, $data);
        $segment = $this->getSegmentModel()->getSegment($segment_id);
        $segment['is_editable'] = $this->getCrmRights()->canEditSegment($segment);
        $this->response = $this->filterFields(
            $segment,
            [
                'id',
                'type',
                'name',
                'hash',
                'sort',
                'create_datetime',
                'contact_id',
                'shared',
                'count',
                'icon',
                'category_id',
                'archived',
                'system_id',
                'app_id',
                'icon_path',
                'is_editable'
            ],
            [
                'id'              => 'integer',
                'contact_id'      => 'integer',
                'count'           => 'integer',
                'archived'        => 'boolean',
                'shared'          => 'boolean',
                'sort'            => 'integer',
                'category_id'     => 'integer',
                'create_datetime' => 'datetime',
                'is_editable'     => 'boolean'
            ]
        );
    }
}
