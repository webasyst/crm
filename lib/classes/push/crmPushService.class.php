<?php

class crmPushService
{
    protected $push_adapter = null;
    protected $onesignal_adapter = null;
    protected $waid_client_id = null;

    public function __construct(array $options = [])
    {
        try {
            $push = wa('crm')->getConfig()->getPushAdapter();
            if ($push->isEnabled()) {
                $this->push_adapter = $push;
            }
            if (empty($this->push_adapter) || $this->push_adapter->getId() !== 'onesignal') {
                $onesignal = wa('crm')->getConfig()->getPushAdapter('onesignal');
                if ($onesignal->isEnabled()) {
                    $this->onesignal_adapter = $onesignal;
                }
            }
        } catch (waException $e) {
            // TODO
        }

        $waidCredentials = (new waAppSettingsModel())->get('webasyst', 'waid_credentials');
        $waidCredentials = json_decode($waidCredentials, true);
        $this->waid_client_id = ifset($waidCredentials['client_id']) ? $waidCredentials['client_id'] : null;
    }

    public function notifyAboutMessage($contact, $message, $conversation, $deal = null)
    {
        if (!isset($message['direction']) || $message['direction'] !== crmMessageModel::DIRECTION_IN) {
            return;
        }
        
        if ((empty($this->push_adapter) && empty($this->onesignal_adapter)) || empty($message)) {
            return;
        }

        if (empty($conversation)) {
            if (!ifset($message['conversation_id'])) {
                return;
            }
            $conversation = (new crmConversationModel)->getById($message['conversation_id']);
            if (empty($conversation)) {
                return;
            }
        }

        if (!empty($conversation['deal_id']) && empty($deal)) {
            $deal = (new crmDealModel)->getById($conversation['deal_id']);
        }
        $crm_user_ids = $this->getMessageUsers($message, $contact, $deal);

        $contacts_push_settings = (new waContactSettingsModel)->getByField(['app_id' => 'crm', 'name' => 'push'], 'contact_id');
        $contacts_push_settings = array_filter($contacts_push_settings, function($settings) use ($message, $crm_user_ids) {
            if (!in_array($settings['contact_id'], $crm_user_ids)) {
                return false;
            }
            $value = json_decode($settings['value'], true);
            if (empty($value)) {
                return false;
            }
            
            return ifset($value['all_messages']) || 
                ifset($value['email_messages']) && $message['transport'] === 'EMAIL' ||
                ifset($value['im_messages']) && $message['transport'] === 'IM' ||
                ifset($value['source_'.$message['source_id'].'_messages']);
        });

        $push_users_ids = array_keys($contacts_push_settings);

        if (!empty($conversation['user_contact_id']) && !in_array($conversation['user_contact_id'], $push_users_ids)) {
            $push_users_ids[] = $conversation['user_contact_id'];
        }
        
        if (empty($push_users_ids)) {
            return;
        }

        $data = [
            'waid_wa_client_id' => $this->waid_client_id,
            'type' => 'MESSAGE',
            'message_id' => (int) $message['id'],
            'conversation_id' => (int) $message['conversation_id']
        ];

        if (empty($contact) && ifset($message['contact_id'])) {
            $contact = new crmContact($message['contact_id']);
        }

        $push_body = ($conversation['type'] === crmConversationModel::TYPE_EMAIL) ? 
        ifset($message['subject'], '') :
        strip_tags($message['body']);
    
        $crm_app_url = wa()->getRootUrl(true) . wa()->getConfig()->getBackendUrl() .'/crm/';
        $client_userpic_url = waContact::getPhotoUrl($contact['id'], $contact['photo'], null, null, $contact['is_company'] ? 'company' : 'person');
        $image_url = $this->getDataResourceUrl($client_userpic_url);

        $sql = "SELECT id, locale FROM `wa_contact` WHERE id IN (:ids) AND is_user = 1";
        $push_users_locales = (new waContactModel)->query($sql, ['ids' => $push_users_ids])->fetchAll();
        $push_users_locales = array_reduce($push_users_locales, function($res, $el) {
            if (!isset($res[$el['locale']])) {
                $res[$el['locale']] = [];
            }
            $res[$el['locale']][] = $el['id'];
            return $res;
        }, []);

        $old_locale = wa()->getLocale();
        foreach($push_users_locales as $locale => $user_ids) {
            wa()->setLocale($locale);

            if (!empty($this->onesignal_adapter)) {
                $this->onesignal_adapter->sendByContact($user_ids, [
                    'title'   => sprintf_wp('New message from %s', $contact->getName()),
                    'message' => $push_body,
                    'url'     => $crm_app_url.'message/conversation/'.$conversation['id'].'/',
                    'image_url' => $image_url,
                    'data' => $data,
                ]);
            }
            if (!empty($this->push_adapter)) {
                $this->push_adapter->sendByContact($user_ids, [
                    'title'   => sprintf_wp('New message from %s', $contact->getName()),
                    'message' => $push_body,
                    'url'     => $crm_app_url.'message/conversation/'.$conversation['id'].'/',
                    'image_url' => $image_url,
                    'data' => $data,
                ]);
            }
        }
        wa()->setLocale($old_locale);
    }

    public function notifyAboutreminder($reminder, $user = null, $contact = null)
    {
        if ((empty($this->push_adapter) && empty($this->onesignal_adapter)) || empty($reminder)) {
            return;
        }

        if (empty($user)) {
            $user = isset($reminder['user_contact_id']) ? new waContact($reminder['user_contact_id']) : null;
        }
        if (empty($user) || empty($user['id'])) {
            return;
        }

        $data = [
            'waid_wa_client_id' => $this->waid_client_id,
            'type' => 'REMINDER',
            'reminder_id' => (int) $reminder['id'],
        ];

        if (empty($contact)) {
            if (ifset($reminder['contact_id']) > 0) {
                $contact = new waContact($reminder['contact_id']);
            } elseif (ifset($reminder['contact_id']) < 0) {
                $deal = (new crmDealModel)->getById(abs($reminder['contact_id']));
                $contact = new waContact(ifset($deal['contact_id']));
            } else {
                $contact = new waContact();
            }
        }
        $crm_app_url = wa()->getRootUrl(true) . wa()->getConfig()->getBackendUrl() .'/crm/';
        $client_userpic_url = waContact::getPhotoUrl($contact['id'], $contact['photo'], null, null, $contact['is_company'] ? 'company' : 'person');

        $locale = isset($user['locale']) ? $user['locale'] : wa()->getLocale();
        $due_time_str = waDateTime::format('humandatetime', $reminder['due_datetime'], null, $locale);
        $reminder_type_str = _w('Reminder');
        switch ($reminder['type']) {
            case 'CALL':
                $reminder_type_str = _w('Call');
                break;
            case 'MESSAGE':
                $reminder_type_str = _w('Message');
                break;
            case 'MEETING':
                $reminder_type_str = _w('Meeting');
                break;
        }
        $push_title = $due_time_str . ' ' . $reminder_type_str;
        $push_body = ((empty($contact['name'])) ? '' : $contact['name'] . ': ') . ifset($reminder['content'], $push_title);
        $image_url = $this->getDataResourceUrl($client_userpic_url);

        if (!empty($this->onesignal_adapter)) {
            $this->onesignal_adapter->sendByContact($user['id'], [
                'title'   => $push_title,
                'message' => $push_body,
                'url'     => $crm_app_url.'reminder/',
                'image_url' => $image_url,
                'data' => $data,
            ]);
        }
        if (!empty($this->push_adapter)) {
            $this->push_adapter->sendByContact($user['id'], [
                'title'   => $push_title,
                'message' => $push_body,
                'url'     => $crm_app_url.'reminder/',
                'image_url' => $image_url,
                'data' => $data,
            ]);
        }
    }

    protected function getDataResourceUrl($relative_url)
    {
        $cdn = wa()->getCdn($relative_url);
        if ($cdn->count() > 0) {
            return (string)$cdn;
        }
        $host_url = wa()->getConfig()->getHostUrl();
        return rtrim($host_url, '/') . '/' . ltrim($relative_url, '/');
    }

    public function getMessageUsers($message, $contact, $deal)
    {
        $admin_conditions = "(r.app_id = 'webasyst' AND r.name = 'backend' AND r.value > 0) OR (r.app_id = 'crm' AND r.name = 'backend' AND r.value > 1)";
        $non_admin_conditions = "(r.app_id = 'crm' AND r.name = 'conversations' AND r.value > " . (empty($message['user_contact_id']) ? 1 : 2) . ")";

        $sql = "SELECT IF(r.group_id < 0, -r.group_id, g.contact_id) AS cid,
                    MAX(IF({$admin_conditions}, 1, 0)) AS is_admin
                FROM wa_contact_rights r
                    LEFT JOIN wa_user_groups g ON r.group_id = g.group_id
                WHERE (r.group_id < 0 OR g.contact_id IS NOT NULL) 
                    AND ({$admin_conditions} OR $non_admin_conditions) 
                GROUP BY cid";

        $model = new waContactRightsModel();
        $user_ids = $model->query($sql)->fetchAll('cid', true);
        if (empty($user_ids)) {
            return [];
        }

        $admin_ids = array_keys(array_filter($user_ids, function($el) {
            return $el == 1;
        }));
        $non_admin_ids = array_keys(array_filter($user_ids, function($el) {
            return $el == 0;
        }));

        if (empty($non_admin_ids)) {
            return $admin_ids;
        }

        if (!empty($contact['crm_vault_id'])) {
            if ($contact['crm_vault_id'] > 0) {
                $conditions = "r.app_id = 'crm' AND r.name = 'vault.{$contact['crm_vault_id']}' AND r.value >= 1";

                $sql = "SELECT DISTINCT IF(r.group_id < 0, -r.group_id, g.contact_id) AS cid
                        FROM wa_contact_rights r
                            LEFT JOIN wa_user_groups g ON r.group_id = g.group_id
                        WHERE (r.group_id < 0 OR g.contact_id IS NOT NULL) AND {$conditions}";

                $vault_user_ids = $model->query($sql)->fetchAll(null, true);
                $non_admin_ids = array_intersect($non_admin_ids, $vault_user_ids);
            } elseif ($contact['crm_vault_id'] < 0) {
                $adhoc_user_ids = array_keys((new crmAdhocGroupModel)->getByField([
                    'adhoc_id'   => -1 * $contact['crm_vault_id'],
                ], 'contact_id'));
                $non_admin_ids = array_intersect($non_admin_ids, $adhoc_user_ids);
            }
        }

        if (empty($non_admin_ids)) {
            return $admin_ids;
        }

        if (!empty($deal)) {
            $participant_ids = array_keys((new crmDealParticipantsModel)->getByField([
                'deal_id' => $deal['id'],
                'role_id' => 'USER',
            ], 'contact_id'));

            $conditions = "r.app_id = 'crm' AND r.name = 'funnel.{$deal['funnel_id']}' AND r.value >= " . (empty($deal['user_id']) ? 2 : 3);

            $sql = "SELECT DISTINCT IF(r.group_id < 0, -r.group_id, g.contact_id) AS cid
                    FROM wa_contact_rights r
                        LEFT JOIN wa_user_groups g ON r.group_id = g.group_id
                    WHERE (r.group_id < 0 OR g.contact_id IS NOT NULL) AND {$conditions}";

            $deal_user_ids = $model->query($sql)->fetchAll(null, true);
            $deal_user_ids = array_unique(array_merge($deal_user_ids, $participant_ids));
            $non_admin_ids = array_intersect($non_admin_ids, $deal_user_ids);
        }

        return array_merge($admin_ids, $non_admin_ids);

        //$sql = "SELECT id FROM `wa_contact` WHERE id IN(:ids) AND is_user = 1";
        //return $model->query($sql, ['ids' => array_merge($admin_ids, $non_admin_ids)])->fetchAll(null, true);
    }
}