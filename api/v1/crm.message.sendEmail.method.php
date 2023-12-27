<?php

class crmMessageSendEmailMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $contact_id = (int) ifset($_json, 'contact_id', 0);
        $subject = trim(ifset($_json, 'subject', ''));
        $body = trim(ifset($_json, 'body', ''));
        $email_to = (string) ifset($_json, 'email_to', '');
        $deal_id = (int) abs(ifempty($_json, 'deal_id', 0));
        $content_type = (string) ifempty($_json, 'content_type', 'plain-text');
        $attachments = ifempty($_json, 'attachments', []);

        if (empty($contact_id) || empty($subject) || empty($body)) {
            throw new waAPIException(
                'required_parameter',
                sprintf_wp(
                    'Missing some of required parameters: %s.',
                    implode(', ', array_map(function ($field) {
                        return sprintf_wp('“%s”', $field);
                    }, ['contact_id', 'subject', 'body']))
                ),
                400
            );
        }

        $contact = new crmContact($contact_id);
        if (!$contact->exists()) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        }
        if (!$this->getCrmRights()->contact($contact)) {
            throw new waAPIException('access_denied', _w('Access denied'), 403);
        }

        if (!in_array($content_type, ['plain-text', 'html'])) {
            throw new waAPIException('invalid_content_type', sprintf_wp('Invalid “%s” value.', 'content_type'), 400);
        } elseif ($content_type === 'plain-text') {
            $body = nl2br(htmlspecialchars($body));
        }
        if (!empty($deal_id)) {
            $deal = $this->getDealModel()->getDeal($deal_id);
            if (empty($deal)) {
                throw new waAPIException('not_found', _w('Deal not found'), 404);
            }
            if (!$this->getCrmRights()->deal($deal)) {
                throw new waAPIException('access_denied', _w('Access denied'), 403);
            }
        }

        /** from */
        $user_emails = waUtils::getFieldValues($this->getUser()->get('email'), 'value');
        $from = reset($user_emails);
        if (empty($from)) {
            throw new waAPIException('empty_email', _w('Empty user email address.'), 400);
        }

        /** to */
        if (empty($email_to)) {
            $contact_emails = waUtils::getFieldValues($contact['email'], 'value');
            $email_to = reset($contact_emails);
            if (empty($email_to)) {
                throw new waAPIException('empty_email', _w('Empty contact email address. Set the “email_to“ value.'), 400);
            }
        } else {
            $email_validator = new waEmailValidator();
            if (!$email_validator->isValid($email_to)) {
                throw new waAPIException(
                    'email_error',
                    implode(', ', $email_validator->getErrors()),
                    400
                );
            }
        }

        /** uses in crmSendEmailController */
        waRequest::setParam([
            'subject'      => $subject,
            'body'         => $this->prepareBody($body),
            'email'        => $email_to,
            'sender_email' => $from,
            'deal_id'      => ifempty($deal_id, 'none'),
            'hash'         => $this->getFiles($attachments),
            'contact_id'   => $contact_id
        ]);

        $email_send_controller = new crmMessageSendNewController();
        $email_send_controller->execute(true);
        $message_id = $email_send_controller->getMessageId();

        if (empty($message_id)) {
            $error = $email_send_controller->getError();
            $error_str = '';
            if (is_array($error)) {
                $error_str = array_reduce($error, function ($res, $item) {
                    if (is_string($item)) {
                        return empty($res) ? $item : $res." \r\n".$item;
                    }
                    return $res;
                });
            } elseif (is_string($error)) {
                $error_str = trim($error);
            }
            if (empty($error_str)) {
                $error_str = _w('Unknown error.');
            }
            throw new waAPIException('send_error', $error_str, 500);
        } else {
            $this->response = ['message_id' => $message_id];
        }
    }

    /**
     * @param $attachments
     * @return string
     * @throws waAPIException
     * @throws waException
     */
    private function getFiles($attachments)
    {
        $hash = md5(uniqid(__METHOD__));
        foreach ((array) $attachments as $_attachment) {
            if (empty($_attachment['file'])) {
                throw new waAPIException('empty_file', sprintf_wp('Missing required parameter: “%s”.', 'file'), 400);
            } elseif (empty($_attachment['file_name'])) {
                throw new waAPIException('empty_file_name', sprintf_wp('Missing required parameter: “%s”.', 'file_name'), 400);
            } elseif (
                in_array(trim($_attachment['file_name']), ['.', '..'])
                || !preg_match('#^[^:*?"<>|/\\\\]+$#', $_attachment['file_name'])
            ) {
                throw new waAPIException('invalid_file_name', _w('Invalid file name.'), 400);
            }

            $temp_path = wa('crm')->getTempPath('mail', 'crm').'/'.$hash;
            waFiles::create($temp_path, true);
            $n = file_put_contents($temp_path."/".$_attachment['file_name'], base64_decode($_attachment['file']));
            if (!$n) {
                throw new waAPIException('server_error', _w('File could not be saved.'), 500);
            }
        }

        return $hash;
    }

    private function prepareBody($body)
    {
        if ($email_signature = (new crmContact($this->getUser()->getId()))->getEmailSignature()) {
            $body .= '<section data-role="c-email-signature"><p><br></p><br>'.$email_signature.'</section>';
        }

        return $body;
    }
}
