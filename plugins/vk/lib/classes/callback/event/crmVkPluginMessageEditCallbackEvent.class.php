<?php

class crmVkPluginMessageEditCallbackEvent extends crmVkPluginMessageNewCallbackEvent
{   
    public function execute()
    {
        if ($this->source->isDisabled()) {
            return 'ok';
        }

        $message = $this->source->findMessage($this->event['object']['id']);
        if (empty($message)) {
            return 'ok';
        }

        $participant = $this->chat->getParticipant();
        $contact = $participant->getContact();
        if ($contact->get('is_user') == -1) {
            // nothing to do, contact is banned
            return 'ok';
        }

        $edited_message = $this->prepareMessage();
        $edited_message['id'] = $message['id'];
        $edited_message['conversation_id'] = $message['conversation_id'];

        $this->source->handleMessageEdit($edited_message, ifset($this->event['object']['update_time'], null));
        
        return 'ok';
    }
}
