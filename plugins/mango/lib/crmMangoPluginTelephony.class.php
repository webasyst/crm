<?php

class crmMangoPluginTelephony extends crmPluginTelephony
{
    public function getRecordHref($call)
    {
        return [
            'href'    => 'javascript:void('.json_encode($call['plugin_record_id']).');',
            'onclick' => 'mangoHandleDownload(event,this,'.json_encode([
                    'r' => $call['plugin_record_id'],
                    'c' => $call['plugin_call_id']
                ]).')',
        ];
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
        $numbers = array();
        $pbx_users = $this->getApi()->getPbxUsers();

        if (!isset($pbx_users['users'])) {
            return $numbers;
        }

        foreach ($pbx_users['users'] as $user)
        {
            if (!empty($user['telephony']['extension'])) {
                $numbers[$user['telephony']['extension']] = $user['general']['name'];
            }
        }

        // Delete all records in crm_pbx
        $this->getPbxModel()->deleteByField(
            array(
                'plugin_id' => 'mango',
            )
        );

        // And add new
        $this->getPbxModel()->multipleInsert(
            array(
                'plugin_id'          => 'mango',
                'plugin_user_number' => array_keys($numbers),
            )
        );

        return $numbers;
    }

    public function isRedirectAllowed($call)
    {
        if ($call['status_id'] == "CONNECTED") {
            return true;
        }
        return false;
    }

    /**
     * Returns candidates to redirect the call.
     *
     * Can use API.
     *
     * The current user should be excluded from the result.
     * @param $call
     * @return array
     */
    public function getRedirectCandidates($call)
    {
        $candidates = $this->getNumbers();
        if (isset($candidates[$call['plugin_user_number']])) {
            unset($candidates[$call['plugin_user_number']]);
        }
        return $candidates;
    }

    /**
     * @param $call
     * @param string $number - Number to which the call will be redirected.
     * @return array
     */
    public function redirect($call, $number)
    {
        return $this->getApi()->redirect($call, $number);
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
        $api = new crmMangoPluginApi();
        return $api;
    }

    public function getRecordUrl($plugin_call_id, $plugin_record_id)
    {
        $api = new crmMangoPluginApi();

        return $api->getRecordUrl($plugin_record_id);
    }
}