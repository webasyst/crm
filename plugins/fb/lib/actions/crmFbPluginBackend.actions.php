<?php

class crmFbPluginBackendActions  extends waActions
{
    public function settingsAction()
    {
        $this->getView()->assign(array(
            'source_settings_url' => wa()->getAppUrl().'settings/sources/?type=im',
        ));

        $template = wa()->getAppPath('plugins/fb/templates/FbPluginSettings.html');
        $this->getView()->display($template);
    }

    public function sendReplyAction()
    {
        $mm = new crmMessageModel();
        $data = waRequest::post();
        $message = $mm->getMessage($data['message_id']);
        if (!$message || !isset($message['params']['fb_contact_id'])) {
            $this->displayJson(null, array('Unkown message'));
            return;
        }

        // Get last message for conversation, if this message outgoing
        if (ifset($message['direction']) == crmMessageModel::DIRECTION_OUT) {
            $cm = new crmConversationModel();
            $conversation = $cm->getConversation($message['conversation_id']);
            $message = $mm->getMessage($conversation['last_message_id']);
            if (!$message || !isset($message['params']['fb_contact_id'])) {
                $this->displayJson(null, array('Unkown message'));
                return;
            }
        }

        $source = crmFbPluginImSource::factory($data['source_id']);
        $sender = new crmFbPluginImSourceMessageSender($source, $message);
        $res = $sender->reply($data);

        if ($res['status'] == 'ok') {
            $this->displayJson($res['response']);
        } else {
            $this->displayJson(null, $res['errors']);
        }
    }
}
