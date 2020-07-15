<?php

/**
 * List of messages.
 * Selects one of two possible views: by conversation or a flat table (all).
 * See crmMessageListByConversationAction (by conversations), crmMessageListAction (all messages).
 */
class crmMessageController extends waViewController
{
    public function execute()
    {
        $storage = wa()->getStorage();
        $mode = 'conversation';

        $view = waRequest::get('view', null, waRequest::TYPE_STRING_TRIM);
        $reload = waRequest::request('reload');
        if ($view) {
            if (!$reload) {
                $storage->set('crm_message_view_mode', $view);
                $mode = $view;
            }
        } else {
            $mode = ($storage->get('crm_message_view_mode')) ? $storage->get('crm_message_view_mode') : $mode;
        }

        if ($mode == 'conversation') {
            $this->executeAction(new crmMessageListByConversationAction());
        } else {
            $this->executeAction(new crmMessageListAction());
        }
    }
}
