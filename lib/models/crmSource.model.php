<?php

class crmSourceModel extends crmModel
{
    protected $table = 'crm_source';

    protected $link_contact_field = array('creator_contact_id', 'responsible_contact_id');

    protected $unset_contact_links_behavior = array(
        'creator_contact_id' => array('set_to', 0),
        'responsible_contact_id' => array('set_to', null)
    );

    /**
     * @var crmSourceParamsModel
     */
    protected $pm;

    const TYPE_EMAIL = 'EMAIL';
    const TYPE_FORM = 'FORM';
    const TYPE_SHOP = 'SHOP';
    const TYPE_IM = 'IM';
    const TYPE_SPECIAL = 'SPECIAL';

    const ICON_TYPE_EMAIL = 'source-email.png';
    const ICON_TYPE_FORM = 'source-form.png';
    const ICON_TYPE_SHOP = 'source-shop.png';
    const ICON_TYPE_IM = 'source-im.png';
    const ICON_TYPE_SPECIAL = '';

    /**
     * @param string $type
     * @param array $data
     * @return bool|int|resource
     * @throws waException
     */
    public function add($type, $data)
    {
        $data['create_datetime'] = date('Y-m-d H:i:s');

        if (!array_key_exists('creator_contact_id', $data)) {
            $data['creator_contact_id'] = wa()->getUser()->getId();
        }
        $data['creator_contact_id'] = (int)$data['creator_contact_id'];

        $type = (string)$type;
        $types = $this->getTypes();
        if (!isset($types[$type])) {
            $type = key($types);
        }
        $data['type'] = $type;

        $data['name'] = (string)ifset($data['name']);
        $data['funnel_id'] = (int)ifset($data['funnel_id']);
        $data['stage_id'] = (int)ifset($data['stage_id']);

        $responsible_contact_id = null;
        if (isset($data['responsible_contact_id'])) {
            $responsible_contact_id = (int)$data['responsible_contact_id'];
        }
        $data['responsible_contact_id'] = $responsible_contact_id;

        $data['disabled'] = (int)ifset($data['disabled']);
        $data['disabled'] = $data['disabled'] > 0 ? 1 : 0;

        $id = $this->insert($data);

        if (!empty($data['params']) && is_array($data['params'])) {
            $this->getSourceParamsModel()->set($id, $data['params']);
        }

        return $id;
    }

    /**
     * @param int $id
     * @param array $data
     * @param bool $delete_old_params If $data['params'] exists this param will pass to set method
     * @see crmSourceParamsModel::set()
     */
    public function update($id, $data, $delete_old_params = true)
    {
        if (!is_array($data) || !wa_is_int($id) || $id <= 0) {
            return;
        }

        // not-editable
        foreach (array('id', 'type', 'creator_contact_id', 'create_datetime') as $field) {
            if (array_key_exists($field, $data)) {
                unset($data[$field]);
            }
        }

        $this->updateById($id, $data);

        if (!array_key_exists('params', $data)) {
            return;
        }

        if (is_array($data['params']) || is_null($data['params'])) {
            $this->getSourceParamsModel()->set($id, $data['params'], $delete_old_params);
        }
    }

    public static function getTypes()
    {
        return array(
            self::TYPE_FORM    => _w('Form source'),
            self::TYPE_EMAIL   => _w('Email source'),
            self::TYPE_SHOP    => _w('Shop source'),
            self::TYPE_SPECIAL => _w('Special source'),
            self::TYPE_IM      => _w('Instant messenger source')
        );
    }

    public static function getIcons()
    {
        return array(
            self::TYPE_FORM    => self::ICON_TYPE_FORM,
            self::TYPE_EMAIL   => self::ICON_TYPE_EMAIL,
            self::TYPE_SHOP    => self::ICON_TYPE_SHOP,
            self::TYPE_SPECIAL => '',
            self::TYPE_IM      => ''
        );
    }

    public function getSources()
    {
        $sources = $this->getAll('id');
        return $this->workup($sources);
    }

    public function getSource($id)
    {
        $source = $this->getById($id);
        if (!$source) {
            return null;
        }
        $sources = array($source['id'] => $source);
        $sources = $this->workup($sources);
        $source = $sources[$source['id']];
        $params = $this->getSourceParamsModel()->get($source['id']);
        $source['params'] = $params;
        return $source;
    }

    public function getEmptySourceOfType($type)
    {
        $types = $this->getTypes();
        if (!isset($types[$type])) {
            $type = 'NULL';
        }
        $source = $this->getEmptyRow();
        $source['type'] = $type;
        $sources = array($source['id'] => $source);
        $sources = $this->workup($sources);
        $source = $sources[$source['id']];
        $source['params'] = array();
        return $source;
    }

    /**
     * @param int|array[]int $id
     */
    public function delete($id)
    {
        $ids = crmHelper::toIntArray($id);
        $ids = crmHelper::dropNotPositive($ids);
        $this->deleteById($ids);
        $this->getSourceParamsModel()->deleteByField('source_id', $ids);
    }

    public function getSourceIdsByParam($param_name, $param_value)
    {
        $param_value = array_map('strval', (array)$param_value);
        if (!$param_value) {
            return array();
        }
        $sql = "SELECT s.id, sp.value AS :param_name
                FROM `crm_source` s
                JOIN `crm_source_params` sp ON sp.source_id = s.id AND sp.name = :param_name
                WHERE sp.value IN(:param_value)
                GROUP BY s.id";
        return $this->query($sql, array(
            'param_name'  => $param_name,
            'param_value' => $param_value
        ))->fetchAll('id');
    }

    public function getActiveEmailSource()
    {
        $sql = "SELECT s.*, sp.value AS email
                FROM `crm_source` s
                JOIN `crm_source_params` sp
                  ON sp.source_id = s.id
                    AND sp.name = 'email'
                JOIN `crm_source_params` sp2
                  ON sp2.source_id = s.id
                    AND sp2.name = 'connection_hash'
                    AND sp2.value IS NOT NULL
                WHERE s.type = 'EMAIL'
                  AND s.disabled = 0
                GROUP BY s.id";
        return $this->query($sql)->fetchAssoc();
    }

    public function addFunnelAndStageInfo($sources)
    {
        $funnels = $this->getFunnelModel()->getAllFunnels();
        $funnels = $this->getFunnelStageModel()->withStages($funnels);

        foreach ($sources as &$source) {
            $funnel_id = (int)$source['funnel_id'];
            $stage_id = (int)$source['stage_id'];
            $source['funnel'] = ifset($funnels, $funnel_id, null);
            $source['stage'] = ifset($stages, $funnel_id, $stage_id, null);
        }
        unset($source);

        return $sources;
    }

    protected function workup($sources)
    {
        $sources_params = $this->getSourceParamsModel()->get(array_keys($sources));
        $all_icons = $this->getIcons();
        foreach ($sources as &$source) {
            $source['icon_url'] = isset($all_icons[$source['type']]) 
                ? wa()->getAppStaticUrl('crm', true).'img/source/'.$all_icons[$source['type']]
                : '';
            $source['backend_url'] = '';
            if ($source['type'] === self::TYPE_FORM) {
                $form_id = ifset($sources_params[$source['id']]['form_id']);
                if ($form_id > 0) {
                    $source['backend_url'] = wa()->getAppUrl('crm') . 'settings/form/' . $form_id . '/';
                }
            }
        }
        unset($source);

        return $sources;
    }
}
