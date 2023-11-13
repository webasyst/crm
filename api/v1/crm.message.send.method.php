<?php

class crmMessageSendMethod extends crmApiAbstractMethod
{
    const FILE_TYPE_IMAGE = 'IMAGE';
    const FILE_TYPE_OTHER = 'OTHER';

    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $reply_body = (string) ifset($_json, 'body', '');
        $reply_message_id = (int) ifset($_json, 'reply_message_id', 0);
        $content_type = (string) ifempty($_json, 'content_type', 'plain-text');
        $attachments = ifempty($_json, 'attachments', []);

        if (empty($reply_body) && empty($attachments)) {
            throw new waAPIException('empty_param', 'One of the parameters is required: body or attachments', 400);
        } elseif (empty($reply_message_id)) {
            throw new waAPIException('empty_reply_message_id', 'Required parameter is missing: reply_message_id', 400);
        } elseif ($reply_message_id < 0) {
            throw new waAPIException('invalid_reply_message_id', 'Invalid reply_message_id', 400);
        }

        $message = $this->getMessageModel()->getMessage($reply_message_id);
        if (!$message) {
            throw new waAPIException('invalid_reply_message_id', 'Message not found', 400);
        } elseif ($message['deal_id'] > 0) {
            $deal = $this->getDealModel()->getDeal($message['deal_id']);
            if (!$this->getCrmRights()->deal($deal)) {
                throw new waAPIException('access_denied', 'Deal access denied', 403);
            }
        } elseif (!$this->getCrmRights()->contact($message['contact_id'])) {
            throw new waAPIException('access_denied', _w('Access denied'), 403);
        } elseif (!($message['source_id'] > 0 || $message['transport'] == crmMessageModel::TRANSPORT_EMAIL)) {
            throw new waAPIException('access_denied', 'Access denied. Reply is not allowed', 403);
        }

        if ($message['transport'] === crmMessageModel::TRANSPORT_IM) {
            if ($message['source_id'] < 1) {
                throw new waAPIException('invalid_reply_message_id', 'Message source not found', 400);
            }
            $source = crmSource::factory($message['source_id']);
            if (empty($source) || $source->isDisabled()) {
                throw new waAPIException('source_switched_off', 'Source switched off', 400);
            }
            $data = [
                'body' => $reply_body,
            ];
            if (!empty($attachments)) {
                $data['hash'] = $this->getFiles($message['transport'], $attachments);
            }
            $result = crmSourceMessageSender::replyToMessage($source, $message, $data);
            if ($result['status'] === 'ok') {
                $this->response = ['message_id' => ifset($result, 'response', 'message_id', null)];
            } else {
                throw new waAPIException('send_error', ifset($result, 'errors', waUtils::jsonEncode($result)), 400);
            }
        } else {
            if (!in_array($content_type, ['plain-text', 'html'])) {
                throw new waAPIException('invalid_content_type', 'Invalid content_type', 400);
            } elseif ($content_type === 'plain-text') {
                $reply_body = nl2br(htmlspecialchars($reply_body));
            }

            $user_emails = waUtils::getFieldValues($this->getUser()->get('email'), 'value');
            $from = reset($user_emails);
            if (empty($from)) {
                throw new waAPIException('empty_email', 'User email empty', 400);
            }

            $cc = [];
            $to = ($message['direction'] === crmMessageModel::DIRECTION_IN ? $message['from'] : $message['to']);
            $message['deal_id'] = ifset($message, 'deal_id', 'none');
            foreach ((array) $message['recipients'] as $_cc => $_recipient) {
                if (
                    $_cc == $to
                    || $_cc == $from
                    || empty($_recipient['contact_id'])
                ) {
                    continue;
                }
                $cc[] = [
                    'id'    => $_recipient['contact_id'],
                    'name'  => $_recipient['name'],
                    'email' => ifset($_recipient, 'destination', '')
                ];
            }

            /** uses in crmSendEmailController */
            waRequest::setParam([
                'subject'      => $this->getSubject($message),
                'body'         => $this->prepareBody($reply_body, $message),
                'email'        => $to,
                'sender_email' => $from,
                'cc'           => $cc,
                'deal_id'      => $message['deal_id'],
                'hash'         => (empty($attachments) ? '' : $this->getFiles($message['transport'], $attachments)),
                'contact_id'   => ifset($message, 'contact_id', ''),
                'conversation_id' => ifset($message, 'conversation_id', null)
            ]);
            $email_send_controller = new crmMessageSendReplyController();
            $email_send_controller->execute($message);
            $message_id = $email_send_controller->getMessageId();

            if (empty($message_id)) {
                throw new waAPIException('send_error', waUtils::jsonEncode($email_send_controller->getError()), 400);
            } else {
                $this->response = ['message_id' => $message_id];
            }
        }
    }

    private function getSubject($message)
    {
        $subject = trim(ifset($message, 'subject', ''));
        $prefix  = substr($subject, 0, 3);
        if (strtolower($prefix) !== 're:') {
            $subject = "Re: $subject";
        }

        return $subject;
    }

    private function prepareBody($body, $message)
    {
        if ($email_signature = (new crmContact($this->getUser()->getId()))->getEmailSignature()) {
            $body .= '<section data-role="c-email-signature"><p><br></p><br>'.$email_signature.'</section>';
        }
        if (!empty($message['body'])) {
            $body .= '<blockquote>'.$message['body'].'</blockquote>';
        }

        return $body;
    }

    /**
     * @param $transport
     * @param $attachments
     * @return array|string
     * @throws waAPIException
     * @throws waException
     */
    private function getFiles($transport, $attachments)
    {
        $prefix = '';
        $hash = md5(uniqid(__METHOD__));
        foreach ((array) $attachments as $_attachment) {
            $_attachment['type'] = ifempty($_attachment, 'type', self::FILE_TYPE_OTHER);
            if (empty($_attachment['file'])) {
                throw new waAPIException('empty_file', 'Required parameter is missing: file', 400);
            } elseif (empty($_attachment['file_name'])) {
                throw new waAPIException('empty_file_name', 'Required parameter is missing: file_name', 400);
            } elseif (!in_array(strtoupper($_attachment['type']), [self::FILE_TYPE_IMAGE, self::FILE_TYPE_OTHER])) {
                throw new waAPIException('invalid_file_type', 'Invalid file type', 400);
            } elseif (
                in_array(trim($_attachment['file_name']), ['.', '..'])
                || !preg_match('#^[^:*?"<>|/\\\\]+$#', $_attachment['file_name'])
            ) {
                throw new waAPIException('invalid_file_name', 'Invalid file name', 400);
            }

            if ($transport === crmMessageModel::TRANSPORT_IM) {
                $prefix = ($_attachment['type'] === self::FILE_TYPE_IMAGE ? 'photos-' : 'files-');
            }
            $temp_path = wa('crm')->getTempPath('mail', 'crm').'/'.$prefix.$hash;
            waFiles::create($temp_path, true);
            $n = file_put_contents($temp_path."/".$_attachment['file_name'], base64_decode($_attachment['file']));
            if (!$n) {
                throw new waAPIException('server_error', 'Can\'t save the file', 500);
            }
        }

        return $hash;
    }
}
