<?php

class crmMessageFlatAction extends crmBackendViewAction
{
    protected $active_sources;
    
    public function execute()
    {
        wa()->getUser()->setSettings('crm', 'messages_flat_mode_enabled', 1);
        waRequest::setParam('is_flat_message_view', 1);
        $listAction = new crmMessageListAction();
        $listAction->execute();
        $message_id = waRequest::param('id', null, waRequest::TYPE_INT);
        $view_param = waRequest::get('view', null, waRequest::TYPE_STRING);
        $iframe = waRequest::request('iframe', 0, waRequest::TYPE_INT);

        if (!empty($iframe) && wa()->whichUI('crm') !== '1.3') {
            $this->setLayout();
            $backend_assets = wa('crm')->event('backend_assets');
        }

        if (empty($message_id)) {
            $messages = $this->view->getVars('list');
            if (!empty($messages)) {
                $available_messages = array_filter($messages, function ($item) {
                    return ifset($item, 'can_view', false);
                });
                if (!empty($available_messages)) {
                    $message = reset($available_messages);
                    $message_id = $message['id'];
                    waRequest::setParam('short_link', 1);
                }
            }
        }
        if (!empty($message_id) && !waRequest::get('no_need_to_get_the_conversation', 0, waRequest::TYPE_INT)) {
            waRequest::setParam('id', $message_id);
            waRequest::setParam('message_id', $message_id);
            (new crmMessageIdAction())->execute();
        }

        $this->view->assign([
            'active_id'      => $message_id,
            'view_param'     => $view_param,
            'iframe'         => $iframe,
            'backend_assets' => ifset($backend_assets, []),
            'is_flat'        => true,
            'content_load_url' => '?module=messageId',
        ]);
        $this->setTemplate('templates/actions/message/MessageConversations.html');
    }
}