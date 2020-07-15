<?php

class crmSipuniPluginTelephony extends crmPluginTelephony
{
    public function getRecordHref($call)
    {
        return array(
            'href'    => 'javascript:void('.json_encode($call['id']).');',
            'onclick' => 'sipuniHandleDownload(event,this,'.json_encode(array(
                'call' => $call['id'],
            )).')',
        );
    }

    public function checkZombieCall($call)
    {
        if ($call['status_id'] == 'PENDING' && (time() - strtotime($call['create_datetime']) > 60*2 )) {
            return array(
                'finish_datetime' => date('Y-m-d H:i:s'),
                'status_id'       => 'DROPPED',
            );
        }
    }

    /**
     * The method returns the phone numbers and address of sip employees of the virtual PBX.
     * Used on: ../webasyst/crm/settings/pbx/
     * @return array
     */
    public function getNumbers()
    {
        $nums = $this->getPbxModel()->getByField(array('plugin_id' => 'sipuni'), true);
        $res = array();
        foreach ($nums as $num) {
            $res[$num['plugin_user_number']] = $num['plugin_user_number'];
        }
        return $res;
    }

    /**
     * Returns a flag: can the plugin create a new call via api
     * @return bool
     */
    public function isInitCallAllowed()
    {
        return true;
    }

    public function initCall($number_from, $number_to, $call)
    {
        $this->getApi()->initCall($number_from, $number_to, $call);
    }

    protected function getApi()
    {
        $api = new crmSipuniPluginApi();
        return $api;
    }
}