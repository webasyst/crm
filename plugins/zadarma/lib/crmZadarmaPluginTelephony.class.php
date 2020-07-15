<?php

class crmZadarmaPluginTelephony extends crmPluginTelephony
{

    public function getRecordHref($call)
    {
        return array(
            'href'    => 'javascript:void('.json_encode($call['plugin_record_id']).');',
            'onclick' => 'zadarmaHandleDownload(event,this,'.json_encode(array(
                    'r' => $call['plugin_record_id'],
                    'c' => $call['plugin_call_id'],
                )).')',
        );
    }

    /**
     * The method returns the phone numbers and address of sip employees of the virtual PBX.
     * Used on: ../webasyst/crm/settings/pbx/
     * @return array
     */
    public function getNumbers()
    {
        $numbers = $this->getApi()->getPbxUsers();
        // Delete all records in crm_pbx
        $this->getPbxModel()->deleteByField(
            array(
                'plugin_id' => 'zadarma',
            )
        );

        // And add new
        $this->getPbxModel()->multipleInsert(
            array(
                'plugin_id'          => 'zadarma',
                'plugin_user_number' => array_keys($numbers),
            )
        );
        return $numbers;
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
     * Returns a flag: can the plugin create a new call via api
     * @return bool
     */
    public function isInitCallAllowed()
    {
        return true;
    }

    public function initCall($number_from, $number_to, $call)
    {
        $this->getApi()->initCall($number_from, $number_to);
    }

    protected function getApi()
    {
        $api = new crmZadarmaPluginApi();
        return $api;
    }
}