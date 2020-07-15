<?php

class crmSipuniPluginApi
{
    public $o;

    const PLUGIN_ID = 'sipuni';
    const API_URL = 'https://sipuni.com/api/';

    public function __construct()
    {
        $this->o = array(
            'user'            => wa()->getSetting('user', '', array('crm', self::PLUGIN_ID)),
            'integration_key' => wa()->getSetting('integration_key', '', array('crm', self::PLUGIN_ID)),
        );

        if (empty($this->o['user'])) {
            throw new waException('Empty user id');
        }

        if (empty($this->o['integration_key'])) {
            throw new waException('Empty integration key');
        }
    }

    public function initCall($from, $to, $call)
    {
        $user = $this->o['user'];
        $phone = intval($to);
        $reverse = '0';
        $antiaon = '0';
        $sipnumber = $from;
        $secret = $this->o['integration_key'];

        $hashString = join('+', array($antiaon, $phone, $reverse, $sipnumber, $user, $secret));
        $hash = md5($hashString);

        $url = 'https://sipuni.com/api/callback/call_number';
        $data = array(
            'antiaon'   => $antiaon,
            'phone'     => $phone,
            'reverse'   => $reverse,
            'sipnumber' => $sipnumber,
            'user'      => $user,
            'hash'      => $hash
        );

        $res = json_decode($this->getNet(array('format' => null))->query($url, $data, waNet::METHOD_POST), true);

        if (!$res['success']) {
            $cm = new crmCallModel();
            $cm->updateById($call['id'], array('status_id' => 'DROPPED', 'finish_datetime' => date('Y-m-d H:i:s')));
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