<?php

class crmTelegramPluginBackendActions  extends waActions
{
    public function checkTokenAction()
    {
        $token = waRequest::request('access_token', null, waRequest::TYPE_STRING_TRIM);
        if (!$token) {
            $this->displayJson(array('message' => 'Access token not passed'), true);
            return;
        }

        $api = new crmTelegramPluginApi($token);
        $bot_data = $api->getMe();

        $this->displayJson($bot_data);
    }

    public function getOldUpdatesAction()
    {
        $token = waRequest::request('access_token', null, waRequest::TYPE_STRING_TRIM);
        if (!$token) {
            $this->displayJson(array('message' => 'Access token not passed'), true);
            return;
        }

        $api = new crmTelegramPluginApi($token);
        $updates = $api->getUpdates();

        $last_update_id = 0;

        if($updates['ok'] && !empty($updates['result'])) {
            foreach ($updates['result'] as $upd) {
                $last_update_id = $upd['update_id'];
            }
        }

        $this->displayJson(array('last_update_id' => $last_update_id));
    }

    public function sendChatActionAction()
    {
        $chat_id = waRequest::request('chat_id', null, waRequest::TYPE_INT);
        if (!$chat_id) {
            $this->displayJson(array('message' => 'Chat id not passed'), true);
            return;
        }
        $action = waRequest::request('action', null, waRequest::TYPE_STRING_TRIM);
        if (!$action) {
            $this->displayJson(array('message' => 'Action not passed'), true);
            return;
        }
        $source_id = waRequest::request('source_id', null, waRequest::TYPE_INT);
        if (!$source_id) {
            $this->displayJson(array('message' => 'Source id not passed'), true);
            return;
        }

        $sm = new crmSourceModel();
        $source = $sm->getSource($source_id);
        if (!$source) {
            $this->displayJson(array('message' => 'Source not found'), true);
            return;
        }
        if (!isset($source['params']['access_token']) || empty($source['params']['access_token'])) {
            $this->displayJson(array('message' => "Access token for source #{$source_id} not found"), true);
            return;
        }

        $api = new crmTelegramPluginApi($source['params']['access_token']);
        $api->sendChatAction($chat_id, $action);
    }

    public function phoneAction()
    {
        $message_id = waRequest::request('message_id', null, waRequest::TYPE_INT);
        if (!$message_id) {
            $this->displayJson(null, ['message' => 'Message id not passed']);
            return;
        }
        $message = (new crmMessageModel)->getMessage($message_id);
        if (empty($message)) {
            $this->displayJson(null, ['message' => 'Message not found']);
            return;
        }
        $source = $this->getSource($message);
        if (empty($source)) {
            $this->displayJson(null, ['message' => 'Source not found']);
            return;
        }

        $no_more_confirmation = waRequest::request('no_more_confirmation', 0, waRequest::TYPE_INT);
        if ($no_more_confirmation) {
            (new waContactSettingsModel)->set(wa()->getUser()->getId(), 'crm.telegram', 'phone_request_no_more_confirmation', 1);
        }

        $button_text = $source->getParam('phone_request_button') or $text = _wd('crm_telegram', 'Send phone number');
        $reply_keyboard = [
            'keyboard' => [[
                [
                    'text' => $button_text,
                    'request_contact' => true
                ]
            ]],
            'one_time_keyboard' => true,
            'resize_keyboard' => true,
        ];

        $text = $source->getParam('phone_request') or $text = _wd('crm_telegram', 'Please send us your phone number.');

        $source_sender = new crmTelegramPluginImSourceMessageSender($source, $message);
        $result = $source_sender->reply(['body' => $text, 'reply_markup' => $reply_keyboard]);
        $error_message = join('<br>', ifset($result['errors'], []));
        $this->displayJson(ifset($result['data']), empty($error_message) ? null : ['message' => $error_message]);
    }

    public function settingsAction()
    {
        $this->getView()->assign(array(
            'source_settings_url' => wa()->getAppUrl().'settings/sources/?type=im',
            'need_show_review_widget' => wa()->appExists('installer')
        ));

        $template = wa()->getAppPath('plugins/telegram/templates/TelegramPluginSettings.html');
        $this->getView()->display($template);
    }

    protected function getSource($message)
    {
        $id = (int)$message['source_id'];
        if ($id <= 0) {
            return null;
        }
        $source = crmSource::factory($id);
        if (!($source instanceof crmTelegramPluginImSource)) {
            return null;
        }
        return $source;
    }
}
