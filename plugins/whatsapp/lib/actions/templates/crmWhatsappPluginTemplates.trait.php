<?php

trait crmWhatsappPluginTemplatesTrait
{
    protected function prepareRequestData()
    {
        $contact_id = waRequest::request('contact_id', null, waRequest::TYPE_INT);
        $conversation_id = waRequest::request('conversation_id', null, waRequest::TYPE_INT);
        $source_id = waRequest::request('source_id', null, waRequest::TYPE_INT);

        if (empty($contact_id) && empty($conversation_id)) {
            throw new waException('Invalid request', 400);
        }

        $conversation = null;
        $cm = new crmConversationModel();
        if (!empty($conversation_id)) {
            $conversation = $cm->getById($conversation_id);
            $contact_id = ifset($conversation['contact_id'], null);
            $source_id = ifset($conversation['source_id'], null);
        } elseif (!empty($source_id) && !empty($contact_id)) {
            $conversation = $cm->getByField([
                'contact_id' => $contact_id,
                'source_id' => $source_id,
            ]);
        } elseif (!empty($contact_id)) {
            $source_ids = array_keys((new crmSourceModel)->getByField(['provider' => 'whatsapp'], 'id'))
            and
            $conversation = $cm->getByField([
                'contact_id' => $contact_id,
                'source_id' => $source_ids,
            ]);
            $source_id = ifset($conversation['source_id'], ifset($source_ids[0]));
        }
        $conversation_id = ifset($conversation['id'], null);

        if (empty($source_id)) {
            throw new waException('Invalid request', 400);
        }

        return [$contact_id, $source_id, $conversation_id];
    }
}