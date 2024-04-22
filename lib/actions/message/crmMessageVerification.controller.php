<?php

class crmMessageVerificationController extends waJsonController
{
    public function execute()
    {
        $message_id = waRequest::request('message_id', null, waRequest::TYPE_INT);
        if (!$message_id) {
            $this->errors = ['message' => 'Message id not passed'];
            return;
        }
        $message = (new crmMessageModel)->getMessage($message_id);
        if (empty($message)) {
            $this->errors = ['message' => 'Message id not found'];
            return;
        }
        
        $source = $this->getSource($message);
        if (empty($source)) {
            $this->errors = ['message' => 'Source id not found'];
            return;
        }

        $verify_url = wa()->getRouteUrl('crm', [
            'module' => 'frontend',
            'action' => 'verification',
            'verification_key' => $source->getParam('verification_key'),
            'message_id' => $message_id,
            'hash'   => crmContactVerifier::createVerificationHash($message['contact_id'], $message_id),
        ], true);

        if (empty($verify_url)) {
            $this->errors = ['message' => '<p>' . _w('A CRM settlement is required.') . '</p><p>' .
                             _w('Use Site app to add a settlement for CRM.') . '</p>'];
            return;
        }

        $no_more_confirmation = waRequest::request('no_more_confirmation', 0, waRequest::TYPE_INT);
        if ($no_more_confirmation) {
            (new waContactSettingsModel)->set(wa()->getUser()->getId(), 'crm', 'verify_no_more_confirmation', 1);
        }

        $button_text = $source->getParam('verify_request_button') ?: _w('Verify client profile');

        $verify_link = [
            'text' => $button_text,
            'url' => $verify_url
        ];

        $verify_text = $source->getParam('verify_request') ?: _w('Please verify your client profile.');

        $source_sender = crmSourceMessageSender::factory($source, $message);
        $source_features = crmSourceHelper::factory($source)->getFeatures();
        $text = $verify_text . (ifset($source_features['html']) ? '<br><br><a href="' . $verify_url . '">'.$button_text.'</a>' : "\r\n\r\n{$button_text}: {$verify_url}");

        $result = $source_sender->reply(['body' => $text, 'verify_body' => $verify_text, 'verify_link' => $verify_link]);
        $error_message = join('<br>', ifset($result['errors'], []));
        $this->response = ifset($result['data']);
        $this->errors = empty($error_message) ? null : ['message' => $error_message];
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