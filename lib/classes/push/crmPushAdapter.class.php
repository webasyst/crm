<?php

class crmPushAdapter extends onesignalPush
{
    public function getId()
    {
        return 'onesignal';
    }

    public function isEnabled()
    {
        return true;
    }

    protected function getNet()
    {
        if (empty($this->net)) {
            $options = [ 'format' => waNet::FORMAT_JSON ];
            $custom_headers = [ 'timeout' => 7 ];
            $this->net = new waNet($options, $custom_headers);
        }

        return $this->net;
    }

    protected function getSubscriberListByField($field, $value)
    {
        // Get only mobile subscribers (scope=crm)
        $fields = array(
            'provider_id' => $this->getId(),
            'scope'       => 'crm',
            $field        => $value,
        );
        $rows = $this->getPushSubscribersModel()->getByField($fields, 'id');

        $subscriber_list = array();
        foreach ($rows as $row) {
            $scope = $row['scope'];
            if (!empty($row['subscriber_data'])) {
                $subscriber_data = json_decode($row['subscriber_data'], true);
                if (!empty($subscriber_data)) {
                    $subscriber_list[] = $subscriber_data;
                }
            }
        }

        $apps = array();
        foreach ($subscriber_list as $subscriber) {
            if (!empty($subscriber['api_app_id']) && !empty($subscriber['api_user_id'])) {
                $apps[$subscriber['api_app_id']][] = $subscriber['api_user_id'];
            }
        }

        return $apps;
    }
}

