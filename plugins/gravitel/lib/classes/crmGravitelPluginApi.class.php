<?php

/**
 * The Gravitel class provides a wrapper for several API methods used
 * @see https://www.gravitel.ru/upload/gravitel_rest_api.pdf
 *
 * Quick Start:
 * @code
 *   $api = new crmGravitelPluginApi();
 *   $api->getPbxUsers();
 */
class crmGravitelPluginApi
{
    const PLUGIN_ID = "gravitel";
    public $o;

    public function __construct()
    {
        $this->o = array(
            'pbx_url' => wa()->getSetting('pbx_url', '', array('crm', self::PLUGIN_ID)),
            'pbx_key' => wa()->getSetting('pbx_key', '', array('crm', self::PLUGIN_ID)),
        );

        if (empty($this->o['pbx_url'])) {
            throw new waException('Empty pbx url');
        }
        if (empty($this->o['pbx_key'])) {
            throw new waException('Empty pbx key');
        }
    }

    public function checkApi()
    {
        try {
            $result = $this->getNet()->query($this->o['pbx_url'], array('cmd' => 'accounts', 'token' => $this->o['pbx_key']));
            return is_array($result);
        } catch (Exception $e) {
            $result = $e->getMessage();
            if (is_string($result) && json_decode($result, true)) {
                $result = json_decode($result, true);
            }
            return empty($result['error']);
        }
    }

    public function getPbxUsers()
    {
        $numbers = array();
        try {
            $result = $this->getNet()->query($this->o['pbx_url'], array('cmd' => 'accounts', 'token' => $this->o['pbx_key']));
        } catch (Exception $e) {
            return $numbers;
        }

        if (!empty($result)) {
            foreach ($result as $user) {
                $numbers[$user['name']] = $user['name'];
            }
        }

        return $numbers;
    }

    public function getHistoryCall($plugin_call_id)
    {
        $data = array(
            'cmd' => 'history',
            'token' => $this->o['pbx_key'],
            'period' => 'today',
        );

        $res = explode("\n", $this->getNet(array('format' => null))->query($this->o['pbx_url'], $data, waNet::METHOD_POST));
        foreach ($res as $call) {
            $call = explode(',', $call);
            if (in_array($plugin_call_id, $call)) {
                return $call;
            }
        }
        return null;
    }

    public function initCall($from, $to, $call)
    {
        $cm = new crmCallModel();
        $data = array(
            'cmd'   => 'makeCall',
            'phone' => $to,
            'user'  => $from,
            'token' => $this->o['pbx_key']
        );
        try {
            $res =  $this->getNet()->query($this->o['pbx_url'], $data, waNet::METHOD_POST);
            if (isset($res['uuid'])) {
                $cm->updateById($call['id'], array('plugin_call_id' => $res['uuid']));
            } else {
                $cm->updateById($call['id'], array('status_id' => 'DROPPED'));
            }
        } catch (Exception $e) {
            $cm->updateById($call['id'], array('status_id' => 'DROPPED'));
            return false;
        }
    }

    protected function getNet($opts = array())
    {
        $params = array();
        return new waNet($opts + array(
                'request_format' => 'raw',
                'format'         => 'json',
            ), $params);
    }
}