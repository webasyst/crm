<?php

class crmSettingsSmsSaveController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $sms = waRequest::post('sms', array());
        $path = $this->getConfig()->getPath('config', 'sms');
        $save = array();


        foreach ($sms as $s) {
            $from = $s['from'];
            $adapter = $s['adapter'];

            if (!$this->isExistsAdapter($adapter)) {
                continue;
            }

            unset($s['from']);
            unset($s['adapter']);
            $empty = true;
            foreach ($s as $v) {
                if ($v) {
                    $empty = false;
                    break;
                }
            }
            if (!$empty) {
                if (!$from) {
                    $from = '*';
                }
                foreach (explode("\n", $from) as $from) {
                    $from = trim($from);
                    $save[$from] = $s;
                    $save[$from]['adapter'] = $adapter;
                }
            }
        }
        waUtils::varExportToFile($save, $path);

        $this->getConfig()->setPhoneTransformPrefix($this->getPhoneTransformPrefix());
    }

    protected function getPhoneTransformPrefix()
    {
        $setting = $this->getRequest()->post('phone_transform_prefix');
        if (!is_array($setting)) {
            $setting = [];
        }
        return $setting;
    }

    protected function isExistsAdapter($adapter)
    {
        $sms_plugin_path = wa()->getConfig()->getPath('plugins').'/sms/';
        return class_exists($adapter . 'SMS') || is_readable($sms_plugin_path.$adapter.'/lib/'.$adapter.'SMS.class.php');
    }
}
