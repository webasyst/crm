<?php

class crmTwitterPluginBackendActions extends waActions
{
    public function checkKeysAction()
    {
        $keys = waRequest::post();
        $fields = array('consumer_key', 'consumer_secret', 'access_token', 'access_token_secret');
        foreach ($fields as $field) {
            if (empty($keys[$field])) {
                $this->displayJson(array('message' => $field.' not passed'), true);
                return;
            }
        }
        $api = new crmTwitterPluginApi($keys);
        $user_data = $api->getMe();

        $this->displayJson($user_data);
    }

    public function getOldUpdatesAction()
    {
        $keys = waRequest::post();
        $fields = array('consumer_key', 'consumer_secret', 'access_token', 'access_token_secret', 'username');
        foreach ($fields as $field) {
            if (empty($keys[$field])) {
                $this->displayJson(array('message' => $field.' not passed'), true);
                return;
            }
        }

        $api = new crmTwitterPluginApi($keys);
        $mentions = (array)$api->getMentions();
        $dms = (array)$api->getDirectMessages();

        $last_mention_id = $last_direct_id = 0;

        if (!empty($mentions) && isset($mentions[0])) {
            $last_mention_id = $mentions[0]['id_str'];
        }

        if (!empty($dms) && !empty($dms['events']) && isset($dms['events'][0])) {
            $last_direct_id = $dms['events'][0]['id'];
        }

        $this->displayJson(array(
            'last_direct_id'  => $last_direct_id,
            'last_mention_id' => $last_mention_id,
        ));
    }

    public function settingsAction()
    {
        $this->getView()->assign(array(
            'source_settings_url' => wa()->getAppUrl().'settings/sources/?type=im',
            'need_show_review_widget' => wa()->appExists('installer')
        ));

        $template = wa()->getAppPath('plugins/twitter/templates/TwitterPluginSettings.html');
        $this->getView()->display($template);
    }

    public function sendReplyAction()
    {
        $mm = new crmMessageModel();
        $data = waRequest::post();
        $message = $mm->getMessage($data['message_id']);
        if (!$message || !isset($message['params']['twitter_user_id']) || $message['source_id'] !== ifset($data['source_id'])) {
            $this->displayJson(null, array('Unkown message'));
            return;
        }

        // Get last message for conversation, if this message outgoing
        if (ifset($message['direction']) == crmMessageModel::DIRECTION_OUT) {
            $cm = new crmConversationModel();
            $conversation = $cm->getConversation($message['conversation_id']);
            $message = $mm->getMessage($conversation['last_message_id']);
            if (!$message || !isset($message['params']['twitter_user_id']) || $message['source_id'] !== ifset($data['source_id'])) {
                $this->displayJson(null, array('Unkown message'));
                return;
            }
        }

        $source = crmTwitterPluginImSource::factory($data['source_id']);
        $sender = new crmTwitterPluginImSourceMessageSender($source, $message);
        $res = $sender->reply($data);

        if ($res['status'] == 'ok') {
            $this->displayJson($res['response']);
        } else {
            $this->displayJson(null, $res['errors']);
        }

    }
}
