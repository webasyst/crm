<?php

class crmMessageConversationsAction extends crmBackendViewAction
{
    public function execute()
    {
        $listAction = new crmMessageListByConversationAction();
        $listAction->execute();
        $conversation_id = waRequest::param('id', null, waRequest::TYPE_INT);
        $view_param = waRequest::get('view', null, waRequest::TYPE_STRING);
        $iframe = waRequest::request('iframe', 0, waRequest::TYPE_INT);

        if (!empty($iframe) && wa()->whichUI('crm') !== '1.3') {
            $this->setLayout();
            $backend_assets = wa('crm')->event('backend_assets');
        }

        if (empty($conversation_id)) {
            $conversations = $this->view->getVars('conversations');
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

        $this->view->assign(array(
            'active_conv'    => $conversation_id,
            'view_param'     => $view_param,
            'iframe'         => $iframe,
            'backend_assets' => ifset($backend_assets, []),
        ));
    }
}