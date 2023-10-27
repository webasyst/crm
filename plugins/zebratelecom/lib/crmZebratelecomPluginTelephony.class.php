<?php

class crmZebratelecomPluginTelephony extends crmPluginTelephony
{
    public function getRecordHref($call)
    {
        return [
            'href'    => 'javascript:void('.json_encode($call['id']).');',
            'onclick' => 'zebratelecomHandleDownload(event,this,'.json_encode([
                    'call' => $call['id']
                ]).')',
        ];
    }

    /**
     * The method returns the phone numbers and address of sip employees of the virtual PBX.
     * Used on: ../webasyst/crm/settings/pbx/
     * @return array
     * @throws waException
     */
    public function getNumbers()
    {
        $result = $this->getApi()->getPbxNumbers();
        $phones = $params = array();
        foreach ($result as $phone) {
            $phones[$phone['name']] = $this->formatUserNumber($phone['name']);
            $params[] = array(
                'plugin_id'          => 'zebratelecom',
                'plugin_user_number' => $phone['name'],
                'name'               => 'device_id',
                'value'              => $phone['id'],
            );
        }

        // Delete all records in crm_pbx
        $this->getPbxModel()->deleteByField(
            array(
                'plugin_id' => 'zebratelecom',
            )
        );
        // And in crm_pbx_params:
        $this->getPbxParamsModel()->deleteByField(
            array(
                'plugin_id' => 'zebratelecom',
            )
        );

        // And add new
        $this->getPbxModel()->multipleInsert(
            array(
                'plugin_id'          => 'zebratelecom',
                'plugin_user_number' => array_keys($phones),
            )
        );
        // And save extension_id (ex. for call init..) in crm_pbx_params:
        $this->getPbxParamsModel()->multipleInsert($params);

        return $phones;
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

    public function isInitCallAllowed()
    {
        return true;
    }

    public function initCall($number_from, $number_to, $call)
    {
        $device_id = $this->getPbxParamsModel()->getByField(array('plugin_id' => 'zebratelecom', 'plugin_user_number' => $number_from, 'name' => 'device_id'));
        $this->getApi()->initCall($device_id['value'], $number_to);
    }

    protected function getApi()
    {
        $api = new crmZebratelecomPluginApi();
        return $api;
    }

    public function getRecordUrl($plugin_call_id, $plugin_record_id)
    {
        return $plugin_record_id;
    }
}
