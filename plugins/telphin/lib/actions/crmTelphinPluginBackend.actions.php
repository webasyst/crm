<?php

class crmTelphinPluginBackendActions extends waActions
{
    // called when user clicks a link to download call record
    public function getRecordLinkAction()
    {
        $plugin_call_id = waRequest::post('c', '', 'string');
        $plugin_record_id = waRequest::post('r', '', 'string');

        try {
            $api = new crmTelphinPluginApi();
            $record_url = $api->getRecordUrl($plugin_record_id);
        } catch (Exception $e) {
            waLog::log("Error fetching URL of record {$plugin_record_id} for call {$plugin_call_id} from API: ".$e->getMessage().' ('.$e->getCode().')', 'crm/plugins/telphin.log');
            $this->displayJson(null, array(
                _wp('Error fetching record URL from Telphin API:').' '.$e->getMessage(),
            ));
            return;
        }

        if ($record_url) {
            $this->displayJson(array(
                'record_url' => $record_url,
            ));
        } else {
            waLog::log("Error fetching URL of record {$plugin_record_id} for call {$plugin_call_id} from API: record does not exist.", 'crm/plugins/telphin.log');

            $call_model = new crmCallModel();
            $call_model->updateByField(array(
                'plugin_record_id' => $plugin_record_id,
            ), array(
                'plugin_record_id' => null,
            ));

            $this->displayJson(null, array(
                _wp('Record does not exist.'),
            ));
        }
    }

    public function settingsAction()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $this->getView()->assign(array(
            'user'           => array(
                'name'    => wa()->getUser()->getName(),
                'company' => wa()->getUser()->get('company'),
                'phone'   => wa()->getUser()->get('phone', 'default'),
                'email'   => wa()->getUser()->get('email', 'default'),
            ),
            'api_app_id'     => wa()->getSetting('api_app_id', null, array('crm', 'telphin')),
            'api_app_secret' => wa()->getSetting('api_app_secret', null, array('crm', 'telphin')),
            'telphin_ask'    => 1, // Once we wanted to send requests to Telphin directly  wa()->getSetting('telphin_ask', null, array('crm', 'telphin')),
            'callback_url'   => $this->getCallbackUrl(),
        ));

        $template = wa()->getAppPath('plugins/telphin/templates/settings.html');
        $this->getView()->display($template);
    }

    public function saveAskAction()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $telphin_ask = waRequest::post('telphin_ask', null, waRequest::TYPE_INT);
        if ($telphin_ask) {
            $this->getPlugin()->saveSettings(array('telphin_ask' => true));
        }
    }

    public function saveRequestDataAction()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $data = waRequest::post('request', null, waRequest::TYPE_ARRAY_TRIM);
        // local validator errors
        $errors = $this->validate($data);
        if ($errors) {
            return $this->displayJson(null, $errors);
        }

        $res = $this->sendRequest($data);
        if (!empty($res['status']) && $res['status'] == 'ok') {
            $this->getPlugin()->saveSettings(array('telphin_ask' => true));
            return $this->displayJson(array());
        }
        // webasyst validator errors
        if (!empty($res['status']) && $res['status'] == 'fail' && !empty($res['errors'])) {
            return $this->displayJson(null, $res['errors']);
        }
    }

    protected function validate($data)
    {
        $errors = null;
        $required_fields = array('person', 'phone', 'email');
        foreach ($required_fields as $f) {
            if (empty(trim($data[$f]))) {
                $errors[] = $f;
            }
        }
        return $errors;
    }

    protected function sendRequest($data)
    {
        if (wa()->appExists('installer')) {
            wa('installer');
            if (class_exists('waInstallerApps')) {
                $installer = new waInstallerApps(); // TODO !!!
                $data['installation_hash'] = $installer->getHash();
            }
        }

        $wa_net = new waNet();
        $serv = $this->getServer();
        $url = "https://{$serv}/my/telphin-signup/";
        $data['domain'] = waRequest::server('HTTP_HOST');
        $res = $wa_net->query($url, array('data' => $data), waNet::METHOD_POST);
        return json_decode($res, true);
    }

    protected function getServer()
    {
        $cfg = wa()->getAppPath('lib/updates/dev/0.php', 'crm');
        if (file_exists($cfg)) {
            return include($cfg);
        }
        return 'webasyst.com';
    }

    protected function getCallbackUrl()
    {
        $routing = wa()->getRouting()->getByApp('crm');
        if (!$routing) {
            return false;
        }
        return rtrim(wa()->getRouteUrl('crm', array(
            'plugin'     => 'telphin',
            'module'     => 'frontend',
            'action'     => 'callback',
            'event_type' => '',
            'auth_hash'  => '',
        ), true), '/');
    }

    protected function getPlugin()
    {
        return waSystem::getInstance()->getPlugin('telphin');
    }
}
