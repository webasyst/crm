<?php

class crmFrontendVerificationAction extends crmFrontendViewAction
{
    public function execute()
    {
        $hash = $this->getParameter('hash');
        if (empty($hash)) {
            $this->getResponse()->setStatus(404);
            return;
        }

        $message_id = (int) $this->getParameter('message_id');
        if (empty($message_id)) {
            $this->getResponse()->setStatus(404);
            return;
        }
        $message = $this->getMessageModel()->getMessage($message_id);
        if (empty($message)) {
            $this->getResponse()->setStatus(404);
            return;
        }

        $contact_id = crmContactVerifier::verify($hash, $message_id);
        if (empty($contact_id)) {
            $this->getResponse()->setStatus(404);
            return;
        }

        $source = $this->getSource($message);
        if (empty($source)) {
            $this->getResponse()->setStatus(404);
            return;
        }

        $verification_key = $this->getParameter('verification_key');
        if ($verification_key != $source->getParam('verification_key')) {
            $this->getResponse()->setStatus(404);
            return;
        }

        $is_contact_updated = false;
        if ($contact_id != wa()->getUser()->getId()) {
            $merger = new crmContactsMerger([
                'master_id'  => wa()->getUser()->getId(),
                'hash'       => 'id/' . $contact_id,
            ]);
            $result = $merger->mergeChunk();

            if (!$result) {
                $this->getResponse()->setStatus(500);
                $this->view->assign([
                    'text' => _w('Unknown error.'),
                ]);
                return;
            }
            $is_contact_updated = true;
        }

        $message['contact_id'] = wa()->getUser()->getId();

        $message_text = $is_contact_updated ? 
            ($source->getParam('verify_done_response') ?: _w('Your client profile has been verified.')) :
            ($source->getParam('verify_been_response') ?: _w('Your client profile was already verified.'));

        $this->view->assign([
            'text' => $message_text,
        ]);

        $source_sender = crmSourceMessageSender::factory($source, $message);
        $source_sender->reply(['body' => $message_text, 'is_auto_response' => true, 'is_contact_updated' => $is_contact_updated]);
    }

    protected function getSource($message)
    {
        $id = (int)$message['source_id'];
        if ($id <= 0) {
            return null;
        }
        return crmSource::factory($id);
    }
}
