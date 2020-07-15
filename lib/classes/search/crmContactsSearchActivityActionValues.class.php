<?php

class crmContactsSearchActivityActionValues
{
    protected $options = array();

    public function __construct($options = array()) {
        $this->options = $options;
    }

    private function getActions($key = false)
    {
        $logs = array(
            "" => array()
        );
        $apps = array_keys($this->getApps());

        $subject = null;
        if (isset($this->options['subject'])) {
            $subject = !!$this->options['subject'];
        }

        foreach ($apps as $app_id) {
            if ($app_id && $app_id !== 'contacts_full') {
                try {
                    $logs[$app_id] = wa($app_id)->getConfig()->getLogActions(true, true);
                    $logs[""] = array_merge($logs[""], wa($app_id)->getConfig()->getSystemLogActions());
                    foreach ($logs[$app_id] as $k => $val) {
                        if ($subject === true && empty($val['subject'])) {
                            unset($logs[$app_id][$k]);
                        }
                    }
                } catch (waException $e) {}
            }
        }

        foreach ($logs[""] as $k => $val) {
            if ($subject === true && empty($val['subject'])) {
                unset($logs[""][$k]);
            }
        }

        $plain_logs = array();
        $names = array();
        foreach ($logs as $app_id => $log) {
            foreach ($log as $l_id => $l) {
                $name = isset($l['name']) ? $l['name'] : $l_id;
                $names[] = $name;
                $plain_logs[] = array(
                    'value' => $app_id ? "{$app_id}--{$l_id}" : $l_id
                );
            }
        }
        asort($names);
        $logs = array();
        foreach ($names as $k => $name) {
            $plain_logs[$k]['name'] = $name;
            $logs[$plain_logs[$k]['value']] = $plain_logs[$k];
        }

        return $key ? $logs : array_values($logs);

    }

    private function parseVal($val)
    {
        if ($val) {
            $app_id = '';
            $action = $val;
            if (strstr($action, '--') !== false) {
                list($app_id, $action) = explode('--', $action);
            }
            return array(
                'app_id' => $app_id, 'action' => $action
            );
        }
        return array(
            'app_id' => '', 'action' => ''
        );
    }


    public function getValues($options)
    {
        return $this->getActions();
    }

    public function where($val_item)
    {
        $val = '';
        if (is_array($val_item)) {
            if (isset($val_item['val'])) {
                $val = $val_item['val'];
            } else {
                $key = key($val_item);
                $val_item = $val_item[$key];
                $val = $key;
            }
        } else {
            $val = $val_item;
        }
        if ($val) {
            $m = new waModel();
            $val = $this->parseVal($val);
            $app_id = $m->escape($val['app_id']);
            $action = $m->escape($val['action']);
            if (!$app_id) {
                $where = "";
            } else {
                $where = ":parent_table.app_id = '{$app_id}' AND ";
            }
            return $where . ":parent_table.action = '{$action}'";
        }
        return '';
    }

    public function extra($val_item)
    {
        $val = '';
        if (is_array($val_item) && isset($val_item['val'])) {
            $val = $val_item['val'];
        } else if (is_string($val_item)) {
            $val = $val_item;
        }
        if ($val) {
            $val = $this->parseVal($val);
            $key = $val['action'];
            if ($val['app_id']) {
                $key = "{$val['app_id']}--{$key}";
            }
            $actions = $this->getActions(true);
            if (isset($actions[$key])) {
                $action = $actions[$key];
                if ($action) {
                    return $action;
                }
            }
            return array('name' => $key);
        }
        return array('name' => $val);
    }

    protected function getApps()
    {
        $apps = array();
        foreach (wa()->getApps() as $app_id => $app) {
            if (wa()->appExists($app_id)) {
                $apps[$app_id] = $app;
            }
        }
        return $apps;
    }
}
