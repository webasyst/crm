<?php

class crmYandextelephonyPluginTelephony extends crmPluginTelephony
{
    public function getNumbers()
    {
        $this->getPbxModel()->deleteByField(array(
            'plugin_id' => 'yandextelephony',
        ));

        try {
            $api = new crmYandextelephonyPluginApi();
        } catch (waException $e) {
            return array();
        }

        $res = array();
        $numbers = $api->getPhoneNumbers();
        foreach ($numbers as $number) {
            $res[$number['number']] = $this->formatUserNumber($number['number']);
        }

        if (!empty($res)) {
            $this->getPbxModel()->multipleInsert(
                array(
                    'plugin_id'          => 'yandextelephony',
                    'plugin_user_number' => array_keys($res),
                )
            );
        }

        return $res;
    }

    // Since there are no adequate api - just kill open calls if they are in standby mode for more than one minute
    public function checkZombieCall($call)
    {
        if ($call['status_id'] == 'PENDING' && (time() - strtotime($call['create_datetime']) > 60 * 2)) {
            return array(
                'finish_datetime' => date('Y-m-d H:i:s'),
                'status_id'       => 'DROPPED',
            );
        }
    }
}