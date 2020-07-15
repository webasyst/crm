<?php

abstract class crmVkPluginChatEntity
{
    protected static $static_cache;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var array
     */
    protected $info;

    /**
     * @var array
     */
    protected $options;

    protected function __construct($id, $options = array())
    {
        $this->id = (int)$id;
        $this->options = $options;
    }

    public function getInfo()
    {
        if ($this->info) {
            if (!array_key_exists('params', $this->info)) {
                $this->info['params'] = $this->obtainParams();
            }
        }
        return $this->info = $this->workupInfo($this->obtainInfo());
    }

    protected function workupInfo($info)
    {
        return $info;
    }

    public function getParams()
    {
        $info = $this->getInfo();
        return $info['params'];
    }

    public function getId()
    {
        return $this->id;
    }

    public function exists()
    {
        return $this->id > 0;
    }

    public function save($data, $delete_old_params = false)
    {
        if ($this->exists()) {
            $this->getEntityModel()->updateById($this->id, $data);
        } else {
            $this->id = $this->getEntityModel()->add($data);
        }
        $this->getEntityParamsModel()->set($this->id, (array)ifset($data['params']), $delete_old_params);
        $this->info = null;
    }

    public function delete()
    {
        if ($this->exists()) {
            $this->getEntityModel()->delete($this->id);
        }
    }

    protected function obtainInfo()
    {
        $info = $this->getEntityModel()->getById($this->id);
        if (!$info) {
            $info = $this->getEntityModel()->getEmptyRow();
        }
        $info['params'] = $this->obtainParams();
        return $info;
    }

    protected function obtainParams()
    {
        return $this->id > 0 ? $this->getEntityParamsModel()->get($this->id) : array();
    }


    /**
     * @return crmVkPluginModel
     */
    abstract protected function getEntityModel();

    /**
     * @return crmVkPluginParamsModel
     */
    abstract protected function getEntityParamsModel();

    /**
     * @param string $model_name
     * @param string $class_name
     * @return crmVkPluginModel|crmVkPluginParamsModel
     */
    protected static function getModel($model_name, $class_name)
    {
        return !empty(self::$static_cache['models'][$model_name]) ?
            self::$static_cache['models'][$model_name] :
            (self::$static_cache['models'][$model_name] = new $class_name());
    }
}
