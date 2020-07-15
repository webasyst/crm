<?php

class crmPaymentModel extends crmModel
{
    protected $table = 'crm_payment';

    /**
     *
     * List available plugins
     * @return array[]
     */
    public function listPlugins()
    {
        $plugins = $this->getAll('id');
        return $plugins;
    }

    public function deleteByField($field, $value = null)
    {
        if (is_array($field)) {
            $items = $this->getByField($field, $this->id);
            $ids = array_keys($items);
        } elseif ($field == $this->id) {
            $ids = $value;
        } else {
            $items = $this->getByField($field, $value, $this->id);
            $ids = array_keys($items);
        }
        $res = false;
        if ($ids) {
            if ($res = parent::deleteByField($this->id, $ids)) {
//                $model = new appSettingsModel();
//                $model->deleteByField('id', $ids);
            }
        }
        return $res;
    }
}
