<?php

class crmMessageConversationsAction extends crmBackendViewAction
{
    public function execute()
    {
        $disable_flat_mode = waRequest::request('disable_flat_mode', 0, waRequest::TYPE_INT);
        $iframe = waRequest::request('iframe', 0, waRequest::TYPE_INT);
        if ($disable_flat_mode) {
            wa()->getUser()->setSettings('crm', 'messages_flat_mode_enabled', 0);
        }

        $conversation_id = waRequest::param('id', null, waRequest::TYPE_INT);
        $flat_mode_enabled = wa()->getUser()->getSettings('crm', 'messages_flat_mode_enabled', 0);
        if (!$disable_flat_mode && !$iframe && empty($conversation_id) && $flat_mode_enabled) {
            $query = waRequest::get();
            unset($query['disable_flat_mode']);
            $query_string = http_build_query($query);
            $url = wa()->getConfig()->getBackendUrl(true) . 'crm/message/flat/';
            if ($query_string !== '') {
                $url .= '?' . $query_string;
            }
            $this->redirect($url);
        }

        $listAction = new crmMessageListByConversationAction();
        $listAction->execute();
        $view_param = waRequest::get('view', null, waRequest::TYPE_STRING);

        if (!empty($iframe) && wa()->whichUI('crm') !== '1.3') {
            $this->setLayout();
            $backend_assets = wa('crm')->event('backend_assets');
        }

        if (empty($conversation_id)) {
            $conversations = $this->view->getVars('list');
            if (!empty($conversations)) {
                $available_conversations = array_filter($conversations, function ($item) {
                    return ifset($item, 'can_view', false);
                });
                if (!empty($available_conversations)) {
                    $conversation = reset($available_conversations);
                    $conversation_id = $conversation['id'];
                    waRequest::setParam('short_link', 1);
                }
            }
        }
        if (!empty($conversation_id) && !waRequest::get('no_need_to_get_the_conversation', 0, waRequest::TYPE_INT)) {
            waRequest::setParam('id', $conversation_id);
            $conversationAction = new crmMessageConversationIdAction();
            $conversationAction->execute();
        }

        $this->view->assign([
            'active_id'      => $conversation_id,
            'view_param'     => $view_param,
            'iframe'         => $iframe,
            'backend_assets' => ifset($backend_assets, []),
            'content_load_url' => '?module=messageConversationId',
        ]);
    }
}