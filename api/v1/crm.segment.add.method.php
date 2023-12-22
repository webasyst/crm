<?php

class crmSegmentAddMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $body_json = $this->readBodyAsJson();
        $name   = trim(ifset($body_json, 'name', ''));
        $type   = trim(ifset($body_json, 'type', crmSegmentModel::TYPE_CATEGORY));
        $type   = ($type === crmSegmentModel::TYPE_SEARCH ? crmSegmentModel::TYPE_SEARCH : crmSegmentModel::TYPE_CATEGORY);
        $hash   = trim(ifset($body_json, 'hash', ''));
        $icon   = trim(ifset($body_json, 'icon', ''));
        $shared = (int) ifset($body_json, 'shared', 0);
        $shared = ($shared === 0 ? 0 : 1);

        if (empty($name)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'name'), 400);
        }
        if ($type === crmSegmentModel::TYPE_SEARCH) {
            if (empty($hash)) {
                throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'hash'), 400);
            }
        }
        if (!in_array($icon, crmSegmentModel::getIcons('2.0'))) {
            $s = $this->prepareSegmentIcons([['icon' => $icon, 'type' => $type]]);
            $icon = reset($s)['icon'];
        }

        $data = [
            'name'     => $name,
            'type'     => $type,
            'hash'     => $hash,
            'icon'     => $icon,
            'shared'   => $shared,
            'contacts' => []
        ];
        $segment_id = $this->getSegmentModel()->add($data);
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
