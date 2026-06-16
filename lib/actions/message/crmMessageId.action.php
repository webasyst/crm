<?php

class crmMessageIdAction extends crmBackendViewAction
{
    public function execute()
    {
        $id = waRequest::get('id', waRequest::param('id', null, waRequest::TYPE_INT), waRequest::TYPE_INT);
        waRequest::setParam('message_id', $id);
        (new crmMessageBodyDialogAction())->execute();
        $message = $this->view->getVars('message');
        $conversation = empty($message['conversation_id']) ? [] : $this->getConversationModel()->getConversation($message['conversation_id']);

        $conversation['icon_url'] = null;
        $conversation['icon'] = null;
        $conversation['icon_fa'] = null;
        $conversation['transport_name'] = _w('Unknown');

        if ($message['transport'] == crmMessageModel::TRANSPORT_EMAIL) {
            $conversation['icon'] = 'email';
            $conversation['icon_fa'] = 'envelope';
            $conversation['transport_name'] = 'Email';
        } elseif ($message['transport'] == crmMessageModel::TRANSPORT_SMS) {
            $conversation['icon'] = 'mobile';
            $conversation['icon_fa'] = 'mobile';
            $conversation['transport_name'] = 'SMS';
        }

        if (!empty($message['source_id'])) {
            $source = crmSource::factory($message['source_id']);
            $source_helper = crmSourceHelper::factory($source);
            $source_features = $source_helper->getFeatures();
            $this->view->assign('source', $source);
            $this->view->assign('source_features', $source_features);

            $res = $source_helper->workupConversation($conversation);
            $conversation = $res ? $res : $conversation;
        }

        $deal = $this->view->getVars('deal');
        if (!empty($deal) && !empty($deal['funnel'])) {
            $this->view->assign('funnel', $deal['funnel']);
        }

        $this->view->assign('conversation', $conversation);

        $can_edit_conversation = empty($conversation) ? true : $this->getCrmRights()->canEditConversation($conversation);
        $this->view->assign('can_edit_conversation', $can_edit_conversation);

        $mrm = new crmMessageReadModel();
        $mrm->setRead($message['id'], wa()->getUser()->getId());
    }
}