<?php

abstract class crmVkPluginModel extends crmModel
{
    public function add($data)
    {
        return $this->insert($data);
    }

    public function delete($id)
    {
        $this->deleteById($id);
    }

    public function updateByField($field, $value, $data = null, $options = null, $return_object = false)
    {
        if ($this->fieldExists('update_datetime')) {
            if (is_array($field)) {
                $return_object = $options;
                $options = $data;
                $data = $value;
                $value = false;
            }
            $data['update_datetime'] = date('Y-m-d H:i:s');
        }
        return parent::updateByField($field, $value, $data, $options, $return_object);
    }

    public function insert($data, $type = 0)
    {
        if ($this->fieldExists('create_datetime')) {
            $data['create_datetime'] = date('Y-m-d H:i:s');
        }
        if ($this->fieldExists('update_datetime')) {
            $data['update_datetime'] = date('Y-m-d H:i:s');
        }
        return parent::insert($data, $type);
    }
}
