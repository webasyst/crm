<?php

class crmCallModel extends crmModel
{
    const DIRECTION_IN = 'IN';
    const DIRECTION_OUT = 'OUT';

    protected $table = 'crm_call';

    protected $call_data = [];

    /**
     * Get list OR|AND count of calls
     * @param $params
     *
     * -  int|bool $params['check_rights']
     *     - <=0 OR ==false - not check rights (default value)
     *     - ==1 OR ==true - check rights, and not extract calls to which current not access to
     *     - >=2 - check rights and extend each call item with flag 'has_access' (true|false)
     *
     * @param null &$total_count
     * @return array|int
     */
    public function getList($params, &$total_count = null)
    {
        $params = is_array($params) ? $params : array();

        $params['check_rights'] = ifset($params['check_rights']);

        // access right
        $right = crmRightConfig::RIGHT_CALL_ALL;
        $user_contact_id = null;
        if ($params['check_rights'] > 0) {   // need to check rights
            $right = wa()->getUser()->getRights('crm', 'calls');
            $user_contact_id = wa()->getUser()->getId();
        }

        if ($right == crmRightConfig::RIGHT_CALL_NONE) {
            if (isset($params['count_results']) && $params['count_results'] === 'only') {
                return 0;
            } else {
                return array();
            }
        }

        // LIMIT
        if (isset($params['offset']) || isset($params['limit'])) {
            $offset = (int) ifset($params['offset'], 0);
            $limit = (int) ifset($params['limit'], 50);
            if (!$limit) {
                return array();
            }
        } else {
            $offset = $limit = null;
        }

        // Count rows setting
        if(!isset($params['count_results']) && func_num_args() > 1) {
            $params['count_results'] = true;
        }
        if (empty($params['count_results'])) {
            $select = "SELECT c.*";
        } else if ($params['count_results'] === 'only') {
            $select = "SELECT count(*)";
        } else {
            $select = "SELECT SQL_CALC_FOUND_ROWS c.*";
        }


        $cond = array();

        // WHERE: filter conditions
        if (isset($params['max_id'])) {
            $cond[] = "id > ".(int)$params['max_id'];
        }
        if (isset($params['id'])) {
            $ids = waUtils::toIntArray($params['id']);
            $ids = array_unique($ids);
            if ($ids) {
                // $ids already typecasted to int, so not use escape
                $cond[] = str_replace(":ids", join(',', $ids), "id IN(:ids)");
            }
        }
        if (isset($params['direction'])) {
            $cond[] = "direction = '".$this->escape($params['direction'])."'";
        }
        if (isset($params['status_id'])) {
            $cond[] = "status_id = '".$this->escape($params['status_id'])."'";
        }
        if (isset($params['user_contact_id'])) {
            $cond[] = "user_contact_id = ".(int)$params['user_contact_id'];
        }
        if (isset($params['client_contact_id'])) {
            $cond[] = 'client_contact_id = '.(int) $params['client_contact_id'];
        }
        if (isset($params['deal_id'])) {
            $cond[] = ($params['deal_id'] == 0 ? 'deal_id IS NULL' : 'deal_id='.(int)$params['deal_id']);
        }
        /** time: --------[begin-------end]-------now */
        if (isset($params['create_datetime_begin'])) {
            $cond[] = "create_datetime >= '".$params['create_datetime_begin']."'";
        }
        if (isset($params['create_datetime_end'])) {
            $cond[] = "create_datetime <= '".$params['create_datetime_end']."'";
        }

        $join = '';

        // join user-operators (pbx_users) not define is call own
        if ($params['check_rights'] == 1 && $right == crmRightConfig::RIGHT_CALL_OWN) {

            $join = "JOIN `crm_pbx_users` pbx ON pbx.plugin_id = c.plugin_id AND pbx.plugin_user_number = c.plugin_user_number AND pbx.contact_id = " . (int)$user_contact_id;

        } elseif ($params['check_rights'] >= 2) {

            // join user-operators (pbx_users) not define is call own
            if ($right == crmRightConfig::RIGHT_CALL_OWN) {

                // if LEFT JOIN become bottleneck, change to differed query and post workup-ing after fetching list
                $join = "LEFT JOIN `crm_pbx_users` pbx ON pbx.plugin_id = c.plugin_id AND pbx.plugin_user_number = c.plugin_user_number AND pbx.contact_id = " . (int)$user_contact_id;

                // flag about accessing
                $select .= ', IF(pbx.contact_id IS NULL, 0, 1) AS has_access';
            } elseif ($right >= crmRightConfig::RIGHT_CALL_ALL) {
                $select .= ', 1 as has_access';
            } else {
                $select .= ', 0 as has_access';
            }
        }

        if ($cond) {
            $cond = 'WHERE '.join(' AND ', $cond);
        } else {
            $cond = '';
        }

        $sql = "{$select}
               FROM {$this->table} AS c
               {$join}
               {$cond}
               ORDER BY id DESC";

        if ($limit) {
            $sql .= " LIMIT $offset, $limit";
        }

        $db_result = $this->query($sql);

        if (empty($params['count_results'])) {
            return $this->postProcessCalls($db_result->fetchAll('id'));
        } else if ($params['count_results'] === 'only') {
            $total_count = $db_result->fetchField();
            return $total_count;
        } else {
            $total_count = $this->query('SELECT FOUND_ROWS()')->fetchField();
            return $this->postProcessCalls($db_result->fetchAll('id'));
        }
    }

    /**
     * @param $calls
     * @return array
     */
    public function postProcessCalls($calls)
    {
        foreach($calls as &$call) {
            $call += array(
                'record_href' => '',
                'plugin_icon' => '',
            );

            /** @var crmPluginTelephony $tplugin */
            $tplugin = wa('crm')->getConfig()->getTelephonyPlugins($call['plugin_id']);
            if ($tplugin) {
                $call['plugin_name'] = $tplugin->getName();
                $call['plugin_icon'] = $tplugin->getIcon();
                if ($call['plugin_record_id']) {
                    $attrs = $tplugin->getRecordHref($call, $tplugin->getId());
                    if (!is_array($attrs)) {
                        $attrs = array(
                            'href' => $attrs,
                        );
                    } else {
                        $attrs += array(
                            'href' => 'javascript:void(0);',
                        );
                    }

                    $call['record_href'] = $attrs['href'];
                    $call['record_attrs'] = $attrs;
                }
                $call['redirect_allowed'] = $tplugin->isRedirectAllowed($call);
            }
        }
        unset($call);

        return $calls;
    }

    public function getOngoingByUser($contact_id = null)
    {
        if (!$contact_id) {
            $contact_id = wa()->getUser()->getId();
        }

        $sql = "SELECT c.*
                FROM {$this->table} AS c
                  INNER JOIN crm_pbx_users AS pu
                    ON pu.plugin_id = c.plugin_id
                      AND pu.plugin_user_number = c.plugin_user_number
                      AND pu.contact_id = ?
                WHERE c.status_id IN ('PENDING','CONNECTED')
                ORDER BY c.id DESC LIMIT 3";
        $calls = $this->query($sql, $contact_id)->fetchAll('id');

        // new calls below
        return array_reverse($calls);
    }

    /**
     * Telephony plugins must call this method after they finish modifying calls in this table.
     */
    public function handleCalls($changed_ids = null)
    {
        static $lm = null;
        if ($lm === null) {
            $lm = new crmLogModel();
        }

        // Make it array(id => id)
        if ($changed_ids !== null) {
            $changed_ids = array_fill_keys($changed_ids, true);
            array_walk($changed_ids, wa_lambda('&$v, $k', '$v = $k;'));
        }

        try {
            $this->findClients($changed_ids);
        } catch (Exception $e) {}

        try {
            $calls_to_notify = $this->notifyUsers($changed_ids);
        } catch (Exception $e) {
            $calls_to_notify = [];
        }

        foreach ($calls_to_notify as $_call_id => $call_to_notify) {
            $contact_id = ifset($call_to_notify, 'client_contact_id', 0);
            if (empty($contact_id)) {
                continue;
            }
            if ($call_to_notify['direction'] === self::DIRECTION_IN) {
                $actor_contact_id = $contact_id;
            } else {
                if (empty($call_to_notify['users_to_notify']) || count($call_to_notify['users_to_notify']) != 1) {
                    $actor_contact_id = null;
                } else {
                    $actor_contact_id = reset($call_to_notify['users_to_notify']);
                    $actor_contact_id = ifset($actor_contact_id, 'id', null);
                }
            }
            $lm->log(
                'call',
                empty($call_to_notify['deal_id']) ? $contact_id : -$call_to_notify['deal_id'],
                $_call_id,
                null,
                null,
                $actor_contact_id
            );
        }
    }

    /** Updates table, fills in client_contact_id using plugin_client_number */
    public function findClients($changed_ids)
    {
        $calls = $this->select('*')
                    ->where("status_id = 'PENDING' AND client_contact_id IS NULL AND plugin_client_number IS NOT NULL")
                    ->fetchAll('id');
        if ($changed_ids) {
            $calls = array_intersect_key($calls, $changed_ids);
        }
        if (!$calls) {
            return;
        }

        foreach ($calls as $id => $call) {
            try {
                /** @var crmPluginTelephony $telephony */
                $telephony = wa('crm')->getConfig()->getTelephonyPlugins($call['plugin_id']);
                if (!$telephony) {
                    continue;
                }
                $contacts = $telephony->findClients($call['plugin_client_number'], 1);
                if (!$contacts || count($contacts) != 1) {
                    continue;
                }

                $c = reset($contacts);
                $this->setCallClient($id, $c['id']);
            } catch (waException $e) {
            }
        }
    }

    public function setCallClient($call_id, $contact_id)
    {
        static $dm = null;
        if ($dm === null) {
            $dm = new crmDealModel();
        }

        $contact_id = (int) $contact_id;
        if (!$contact_id) {
            $contact_id = null;
        }

        $upd = array('client_contact_id' => $contact_id);

        if ($contact_id) {
            $deals = $dm->select('*')->where("status_id = 'OPEN' AND contact_id = ?", $contact_id)->fetchAll('id');
            if (count($deals) == 1) {
                $deal = reset($deals);
                $upd['deal_id'] = $deal['id'];
            }
        } else {
            $upd['deal_id'] = null;
        }

        $this->updateById($call_id, $upd);
    }

    /**
     * Send push notifications to users about pending calls assigned to them.
     * @var array $changed_ids ids for changed calls
     * @throws waException
     */
    public function notifyUsers($changed_ids)
    {
        if (empty($changed_ids)) {
            return [];
        }

        $sql = "SELECT *
                FROM {$this->table}
                WHERE notification_sent = 0
                  AND id IN (?)";
        $calls_to_notify = $this->query($sql, array($changed_ids))->fetchAll('id');

        if (empty($calls_to_notify)) {
            return [];
        }

        // Which users are responsible for which calls
        $pbx_users = $this->getPbxUsersModel()->getPbxUsers();

        if (!empty($calls_to_notify)) {
            // Fetch list of users to notify
            $this->getUsersToNotify($calls_to_notify, $pbx_users);

            // Send notifications
            $this->pushToUsers($calls_to_notify);

            // Remember calls we no longer need to send notifications about
            $sql = "UPDATE {$this->table}
                    SET notification_sent = 1
                    WHERE id IN (?)";
            $this->exec($sql, array(array_keys($calls_to_notify)));
        }

        // Calls that changed their state from pending since last notification
        $no_longer_pending = array();
        foreach ($calls_to_notify as $c_id => $c) {
            if ($c['status_id'] !== 'PENDING') {
                $no_longer_pending[$c_id] = $c;
            }
        }

        // Calls currently pending
        $pending = $this->getByField('status_id', 'PENDING', 'id');

        // Update user_contact_id for calls
        $this->updateEmptyUserContactIds($pending + $no_longer_pending, $pbx_users);

        return $calls_to_notify;
    }

    public function getUsersToNotify(array &$calls, $pbx_users)
    {
        $users = array();
        foreach ($calls as $call_id => &$call) {
            $call['users_to_notify'] = array();

            if (empty($pbx_users[$call['plugin_id']][$call['plugin_user_number']])) {
                continue;
            }

            foreach ($pbx_users[$call['plugin_id']][$call['plugin_user_number']] as $user_id) {
                $users[] = $user_id;
                $call['users_to_notify'][$user_id] = ['id' => $user_id];
            }

        }
        unset($call);
    }

    protected function pushToUsers($calls_to_notify)
    {
        try {
            $push = wa()->getPush();
            if (!$push->isEnabled()) {
                return false;
            }

            $crm_app_url = wa()->getRootUrl(true) . wa()->getConfig()->getBackendUrl() .'/crm/';

            $calls_client_ids = array();
            foreach ($calls_to_notify as $call) {
                if (!empty($call['client_contact_id']) && !in_array($call['client_contact_id'], $calls_client_ids)) {
                    $calls_client_ids[] = $call['client_contact_id'];
                }
            }

            // Get call clients data
            $collection = new waContactsCollection($calls_client_ids);
            $calls_client_contacts = $collection->getContacts('id,name,photo,is_company');

            // Get open deals for all contacts
            $contacts_deals = $this->getDealModel()->getOpenDeals(array_keys($calls_client_contacts));

            foreach ($calls_to_notify as $call) {
                if (empty($call['users_to_notify'])) {
                    continue;
                }

                $contact_ids = array();
                foreach ($call['users_to_notify'] as $user_to_push) {
                    $contact_ids[] = $user_to_push['id'];
                }

                if (empty($contact_ids)) {
                    continue;
                }

                // Prepare call client contact and deal data
                if (!empty($call['client_contact_id']) && !empty($calls_client_contacts[$call['client_contact_id']])) {
                    $call['client_contact'] = $calls_client_contacts[$call['client_contact_id']];
                }

                $client_phone_formatted = $this->formatPhone($call['plugin_client_number']);
                $data = array(
                    'title'   => _w('New call'),
                    'message' => $client_phone_formatted,
                    'url'     => $crm_app_url.'contact/new/?call='.$call['id'].'&phone='.urlencode($client_phone_formatted),
                );

                if (!empty($call['client_contact_id']) && !empty($calls_client_contacts[$call['client_contact_id']])) {
                    $call_contact = $calls_client_contacts[$call['client_contact_id']];

                    $data['title'] .= ': '. $call_contact['name'];
                    $data['url'] = $crm_app_url.'contact/'.$call_contact['id'].'/deals/';

                    $call_contact['deals'] = array();
                    foreach ($contacts_deals as $contacts_deal) {
                        if ($contacts_deal['contact_id'] == $call_contact['id']) {
                            $call_contact['deals'][$contacts_deal['id']] = $contacts_deal;
                        }
                    }

                    $call_contact_deals_count = (!count($call_contact['deals'])) ? _w('No open deals') : _w('%d open deal', '%d open deals', count($call_contact['deals']));
                    $data['message'] .= "\n{$call_contact_deals_count}";

                    $request_domain = waRequest::server('HTTP_HOST');
                    $client_userpic_url = waContact::getPhotoUrl($call_contact['id'], $call_contact['photo'],null, null, $call_contact['is_company'] ? 'company' : 'person');

                    $data['image_url'] = "https://{$request_domain}{$client_userpic_url}";
                }

                $push->sendByContact($contact_ids, $data);
            }
        } catch (Exception $e) {
            // Oh, well..
        }
    }

    protected function updateEmptyUserContactIds($calls, $pbx)
    {
        foreach($calls as $call) {
            if (!empty($call['user_contact_id'])) {
                continue;
            }
            $user_contact_ids = ifset($pbx, $call['plugin_id'], $call['plugin_user_number'], array());
            if (count($user_contact_ids) == 1) {
                $this->updateById($call['id'], array(
                    'user_contact_id' => reset($user_contact_ids),
                ));
            }
        }
    }

    public function insert($data, $type = 0)
    {
        $res = parent::insert($data, $type);

        $asm = new waAppSettingsModel();
        $asm->set('crm', 'call_ts', time());

        return $res;
    }

    public function getById($value)
    {
        $this->call_data = parent::getById($value);

        return $this->call_data;
    }

    public function updateById($id, $data, $options = null, $return_object = false)
    {
        $res = parent::updateById($id, $data, $options, $return_object);

        $asm = new waAppSettingsModel();
        $asm->set('crm', 'call_ts', time());

        return $res;
    }

    public function updateByField($field, $value, $data = null, $options = null, $return_object = false)
    {
        $res = parent::updateByField($field, $value, $data, $options, $return_object);

        $asm = new waAppSettingsModel();
        $asm->set('crm', 'call_ts', time());

        if (ifset($field, 'status_id', '') === 'FINISHED' || ifset($data, 'status_id', '') === 'FINISHED') {
            if (empty($this->call_data) || ($field === 'id' && $this->call_data['id'] != $value)) {
                $this->call_data = $this->getById((int) $value);
            }
            if ($this->call_data['status_id'] !== 'FINISHED' && !empty($this->call_data['user_contact_id'])) {
                $params = [
                    'call_id'  => $this->call_data['id'],
                    'duration' => ifset($data, 'duration', $this->call_data['duration'])
                ];
                if (!empty($this->call_data['deal_id'])) {
                    $params['deal_id'] = $this->call_data['deal_id'];
                }
                if (!class_exists('waLogModel')) {
                    wa('webasyst');
                }
                (new waLogModel())->add(
                    ($this->call_data['direction'] === self::DIRECTION_IN ? 'call_in' : 'call_out'),
                    $params,
                    ifempty($this->call_data, 'client_contact_id', null),
                    $this->call_data['user_contact_id']
                );
            }
        }

        return $res;
    }

    public function deleteById($value)
    {
        $res = parent::deleteById($value); // TODO: Change the autogenerated stub

        $asm = new waAppSettingsModel();
        $asm->set('crm', 'call_ts', time());

        return $res;
    }

    protected function formatPhone($phone)
    {
        class_exists('waContactPhoneField');
        $formatter = new waContactPhoneFormatter();
        return $formatter->format(waContactPhoneField::cleanPhoneNumber($phone));
    }
}
