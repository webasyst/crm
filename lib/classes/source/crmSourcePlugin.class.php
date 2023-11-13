<?php

abstract class crmSourcePlugin extends waPlugin
{
    protected static $static_cache;

    /**
     * @return array[]
     */
    public static function getPlugins()
    {
        if (isset(self::$static_cache['configs'])) {
            return self::$static_cache['configs'];
        }
        $plugins = wa('crm')->getConfig()->getPlugins();
        foreach ($plugins as $id => $plugin) {
            if (empty($plugin['source'])) {
                unset($plugins[$id]);
            }
        }
        return self::$static_cache['configs'] = $plugins;
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        return $this->info;
    }

    public function uninstall($force = false)
    {
        $sm = new crmSourceModel();
        $ids = $sm->select('id')->where('type = :type AND provider = :provider', array(
            'type' => crmSourceModel::TYPE_IM,
            'provider' => $this->id
        ))->fetchAll(null, true);
        if ($ids) {
            $sm->delete($ids);
        }
        parent::uninstall($force);
    }

    /**
     * @param $id
     * @return array|null
     */
    public static function getPlugin($id)
    {
        $plugins = self::getPlugins();
        return ifset($plugins[$id]);
    }

    /**
     * @param $id
     * @return null|crmSourcePlugin|crmSourcePlugin[]
     */
    public static function factory($id = null)
    {
        if (waConfig::get('is_template')) {
            return null;
        }

        if ($id === null || is_array($id)) {
            $instances = array();
            foreach (self::getPlugins() as $config) {
                if (is_array($id) && !in_array($config['id'], $id, true)) {
                    continue;
                }
                $instance = self::factory($config['id']);
                if ($instance) {
                    $instances[$config['id']] = $instance;
                }
            }
            return $instances;
        }

        $info = self::getPlugin($id);
        if (!$info) {
            return null;
        }

        $id = strtolower((string)trim($id));

        $part_of_name = ucfirst($id);
        $class_name = "crm{$part_of_name}Plugin";
        if (!class_exists($class_name)) {
            return null;
        }
        $instance = new $class_name($info);
        if (!($instance instanceof crmSourcePlugin)) {
            return null;
        }

        return $instance;
    }

    /**
     * Never static, cause instance of source tied to current plugin instance
     *
     * @param $id
     * @param array $options
     * @return crmSource
     */
    abstract public function factorySource($id, $options = array());
}
