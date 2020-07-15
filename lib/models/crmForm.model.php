<?php

class crmFormModel extends crmModel
{
    protected $table = 'crm_form';

    /**
     * @param int $id
     * @return array
     */
    public function getForm($id)
    {
        $form = $this->getById($id);
        if ($form) {
            $form['params'] = $this->getFormParamsModel()->get($id);
        }
        return $form;
    }

    /**
     * @param $data
     * @return bool|int|resource
     */
    public function add($data)
    {
        $data['create_datetime'] = date('Y-m-d H:i:s');
        if (!array_key_exists('contact_id', $data)) {
            $data['contact_id'] = wa()->getUser()->getId();
        }
        $data['contact_id'] = (int)$data['contact_id'];
        $data['name'] = (string)ifset($data['name']);

        if (isset($data['id'])) {
            unset($data['id']);
        }

        $id = $this->insert($data);

        if (!empty($data['params']) && is_array($data['params'])) {
            $this->getFormParamsModel()->set($id, $data['params']);
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
        foreach (array('id', 'contact_id', 'create_datetime') as $field) {
            if (array_key_exists($field, $data)) {
                unset($data[$field]);
            }
        }

        $this->updateById($id, $data);

        if (!array_key_exists('params', $data)) {
            return;
        }

        if (is_array($data['params']) || is_null($data['params'])) {
            $this->getFormParamsModel()->set($id, $data['params'], $delete_old_params);
        }
    }


    /**
     * @param int|int[] $id
     */
    public function delete($id)
    {
        $ids = crmHelper::toIntArray($id);
        $ids = crmHelper::dropNotPositive($ids);
        $this->deleteById($ids);
        $this->getFormParamsModel()->deleteByField('form_id', $ids);
    }

    /**
     * @return array
     */
    public function getAllFormsForControllers()
    {
        $forms = $this->getFormModel()->getAllOrdered('id', 'id');

        $form_ids = waUtils::getFieldValues($forms, 'id');
        $form_ids = crmHelper::toIntArray($form_ids);
        $form_ids = crmHelper::dropNotPositive($form_ids);

        if (!$form_ids) {
            return $forms;
        }

        $form_id_source_id_map = array();

        $where = array(
            'form_id' => $form_ids,
            'name' => 'source_id'
        );
        $items = $this->getFormParamsModel()->getByField($where, true);
        foreach ($items as $item)
        {
            $source_id = (int)$item['value'];
            if ($source_id > 0) {
                $form_id_source_id_map[$item['form_id']] = (int)$item['value'];
            }
        }

        $source_ids = array_unique(array_values($form_id_source_id_map));
        if (!$source_ids) {
            return $forms;
        }

        $sources = $this->getSourceModel()
            ->select('*')
            ->where('id IN (:ids) AND disabled = 0',
                array('ids' => $source_ids)
            )->fetchAll('id');
        $sources = $this->getSourceModel()->addFunnelAndStageInfo($sources);

        foreach ($forms as &$form) {

            $form_id = $form['id'];
            if (!isset($form_id_source_id_map[$form_id])) {
                continue;
            }

            $source_id = $form_id_source_id_map[$form_id];
            if (!isset($sources[$source_id])) {
                continue;
            }

            $source = $sources[$source_id];

            $form['funnel'] = $source['funnel'];
            $form['stage'] = $source['stage'];

        }
        unset($form);

        return $forms;
    }

}
