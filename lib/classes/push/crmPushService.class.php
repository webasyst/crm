<?php

class crmPushService
{
    protected $push_adapter = null;
    protected $waid_client_id = null;

    public function __construct(array $options = [])
    {
        try {
            $push = wa('crm')->getConfig()->getPushAdapter();
            if ($push->isEnabled()) {
                $this->push_adapter = $push;
            }
        } catch (waException $e) {
            // TODO
        }

        $waidCredentials = (new waAppSettingsModel())->get('webasyst', 'waid_credentials');
        $waidCredentials = json_decode($waidCredentials, true);
        $this->waid_client_id = ifset($waidCredentials['client_id']) ? $waidCredentials['client_id'] : null;
    }

    public function notifyAboutMessage($contact, $message, $conversation)
    {
        if (empty($this->push_adapter) || empty($message)) {
            return;
        }

        //$rights_model = new waContactRightsModel();
        //$crm_user_ids = $rights_model->getUsers('crm');

        $data = [
            'waid_wa_client_id' => $this->waid_client_id,
            'type' => 'MESSAGE',
            'message_id' => (int) $message['id'],
            'conversation_id' => (int) $message['conversation_id']
        ];

        if ($message['direction'] === crmMessageModel::DIRECTION_IN) {
            if (empty($conversation)) {
                if (!ifset($message['conversation_id'])) {
                    return;
                }
                $conversation = (new crmConversationModel)->getById($message['conversation_id']);
                if (empty($conversation)) {
                    return;
                }
            }
            
            if (!empty($conversation['user_contact_id'])) {
                // Видимый пуш ответственному
                if (empty($contact) && ifset($message['contact_id'])) {
                    $contact = new crmContact($message['contact_id']);
                }
        
                $push_body = ($conversation['type'] === crmConversationModel::TYPE_EMAIL) ? 
                    ifset($message['subject'], '') :
                    strip_tags($message['body']);
                
                $crm_app_url = wa()->getRootUrl(true) . wa()->getConfig()->getBackendUrl() .'/crm/';
                $client_userpic_url = waContact::getPhotoUrl($contact['id'], $contact['photo'], null, null, $contact['is_company'] ? 'company' : 'person');
                
                $user = new waContact($conversation['user_contact_id']);
                if (!$user->exists()) {
                    return;
                }

                $old_locale = wa()->getLocale();
                if ($user->getLocale() !== $old_locale) {
                    wa()->setLocale($user->getLocale());
                }

                $this->push_adapter->sendByContact($conversation['user_contact_id'], [
                    'title'   => sprintf_wp('New message from %s', $contact->getName()),
                    'message' => $push_body,
                    'url'     => $crm_app_url.'message/conversation/'.$conversation['id'].'/',
                    'image_url' => $this->getDataResourceUrl($client_userpic_url),
                    'data' => $data,
                ]);

                if ($user->getLocale() !== $old_locale) {
                    wa()->setLocale($old_locale);
                }
                /*
                $crm_user_ids = array_filter($crm_user_ids, function ($el) use ($conversation) {
                    return $el != $conversation['user_contact_id'];
                });
                */
            }
        }
        /*
        if (!empty($crm_user_ids)) {
            // технический скрытый пуш всем осталным
            $this->push_adapter->sendByContact($crm_user_ids, [
                'data' => $data,
            ]);
        }
        */
    }

    public function notifyAboutreminder($reminder, $user = null, $contact = null)
    {
        if (empty($this->push_adapter) || empty($reminder)) {
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

        $this->push_adapter->sendByContact($user['id'], [
            'title'   => $push_title,
            'message' => $push_body,
            'url'     => $crm_app_url.'reminder/',
            'image_url' => $this->getDataResourceUrl($client_userpic_url),
            'data' => $data,
        ]);
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
}