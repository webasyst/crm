<?php

class crmGravitelPluginTelephony extends crmPluginTelephony
{
    public function getRecordHref($call)
    {
        return array(
            'href'    => 'javascript:void('.json_encode($call['id']).');',
            'onclick' => 'gravitelHandleDownload(event,this,'.json_encode(array(
                    'call' => $call['id'],
                )).')',
        );
    }

    public function checkZombieCall($call)
    {
        // Is this a finished call?
        try {
            $gravitel_call = $this->getApi()->getHistoryCall($call['plugin_call_id']);
            if ($gravitel_call) {
                $time = new DateTime($call['create_datetime']);
                $time->add(new DateInterval('PT'.$gravitel_call['7'].'S'));
                $finish_datetime = $time->format('Y-m-d H:i:s');

                return array(
                    'duration'         => $gravitel_call['7'],
                    'finish_datetime'  => $finish_datetime,
                    'status_id'        => $gravitel_call['7'] > 0 ? 'FINISHED' : 'DROPPED',
                    'plugin_record_id' => ($gravitel_call['8']) ? $gravitel_call['7'] : null,
                );
            }
        } catch (Exception $e) {}

        if (time() - strtotime($call['create_datetime']) > 60 * 30) {
            return array(
                'finish_datetime' => date('Y-m-d H:i:s'),
                'status_id'       => $call['status_id'] == 'PENDING' ? 'DROPPED' : 'FINISHED',
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
        $pbx_users = $this->getApi()->getPbxUsers();
        if (empty($pbx_users)) {
            return array();
        }

        // Delete all records in crm_pbx
        $this->getPbxModel()->deleteByField(
            array(
                'plugin_id' => 'gravitel',
            )
        );

        // And add new
        $this->getPbxModel()->multipleInsert(
            array(
                'plugin_id'          => 'gravitel',
                'plugin_user_number' => array_keys($pbx_users),
            )
        );

        return $pbx_users;
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
        $api = new crmGravitelPluginApi();
        return $api;
    }
}