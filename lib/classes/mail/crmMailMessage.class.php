<?php

class crmMailMessage
{
    protected $mail;

    const CONSTRUCT_TYPE_PATH = 'path';
    const CONSTRUCT_TYPE_CONTENT = 'content';

    /**
     * crmMailMessage constructor.
     * @param $mail
     * @param string $type
     *          self::CONSTRUCT_TYPE_PATH
     *          self::CONSTRUCT_TYPE_CONTENT
     *          ...
     * @throws waException
     */
    public function __construct($mail, $type = self::CONSTRUCT_TYPE_PATH)
    {
        if ($type === self::CONSTRUCT_TYPE_CONTENT) {
            $temp_path = wa('crm')->getTempPath('mail', 'crm');
            $file_path = $temp_path . '/' . md5(uniqid(substr($mail, 0, 16), true));
            file_put_contents($file_path, $mail);
            $mail = $file_path;
        }
        if (!is_string($mail) || !file_exists($mail)) {
            throw new waException('Must be existed mail path');
        }
        $mail_decode = new crmMailDecoder(array(
            'max_attachments' => -1
        ));
        $this->mail = $mail_decode->decode($mail);
        $this->mail['source_path'] = $mail;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        if (isset($this->mail['__body'])) {
            return $this->mail['__body'];
        }
        // Message body: try HTML, then use plain if no HTML found
        $text = ifset($this->mail['text/html']);
        if(empty($text)) {
            $text = (string)ifset($this->mail['text/plain']);
            $text = nl2br(htmlspecialchars($text, ENT_IGNORE));
        }

        return $this->mail['__body'] = $text;
    }

    /**
     * @return string
     */
    public function getMessageId()
    {
        return (string)ifset($this->mail['headers']['message-id']);
    }

    public function getInReplyTo()
    {
        $item = (string)ifset($this->mail['headers']['in-reply-to']);
        $item = trim($item);
        if (substr($item, 0, 1) === '<') {
            $item = substr($item, 1);
        }
        if (substr($item, -1, 1) === '>') {
            $item = substr($item, 0, -1);
        }
        $item = trim($item);
        return $item;
    }

    public function getReferences()
    {
        $references = (string)ifset($this->mail['headers']['references']);
        $references = preg_split('/[\s<>]+/', $references);
        $references = array_map(function ($item) {
            return trim($item, '<>'); 
        }, $references);
        
        return array_values(array_filter($references));
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return (string)ifset($this->mail['headers']['subject']);
    }

    /**
     * @return array
     */
    public function getRecipients()
    {
        if (isset($this->mail['__recipients'])) {
            return $this->mail['__recipients'];
        }
        $recipients = array();
        foreach ($this->getDirectRecipients() as $recipient) {
            $recipients[] = $recipient;
        }
        foreach ($this->getCopies() as $copy) {
            $recipients[] = $copy;
        }
        foreach ($this->getBlindCopies() as $copy) {
            $recipients[] = $copy;
        }
        return $this->mail['__recipients'] = $recipients;

    }

    public function getDirectRecipients()
    {
        if (isset($this->mail['__direct_recipients'])) {
            return $this->mail['__direct_recipients'];
        }
        $recipients = $this->workupRecipients((array)ifset($this->mail['headers']['to']));
        foreach ($recipients as &$recipient) {
            $recipient['type'] = crmMessageRecipientsModel::TYPE_TO;
        }
        unset($recipient);
        return $this->mail['__direct_recipients'] = $recipients;
    }


    /**
     * @return string
     */
    public function getReplyEmail()
    {
        $to = $this->getReplyTo();
        return $to['email'];
    }

    public function getReplyName()
    {
        $to = $this->getReplyTo();
        return $to['name'];
    }

    public function getReplyFullString()
    {
        $to = $this->getReplyTo();
        return $to['full'];
    }

    /**
     * @return array
     */
    public function getReplyTo()
    {
        if (isset($this->mail['__reply_to'])) {
            return $this->mail['__reply_to'] !== false ? $this->mail['__reply_to'] : null;
        }

        $names = array();
        $valid_emails = array();

        $headers = array('reply-to', 'from', 'envelope-from');
        foreach ($headers as $header) {
            if (!empty($this->mail['headers'][$header])) {
                $value = $this->mail['headers'][$header];
                if (is_string($value)) {
                    $email = $value;
                    if ($this->isValidEmail($email)) {
                        $valid_emails[] = $email;
                    }
                }
                if (is_array($value) && isset($value['email'])) {
                    if ($this->isValidEmail($value['email'])) {
                        $valid_emails[] = $value['email'];
                    }
                    if (isset($value['name'])) {
                        $names[] = $value['name'];
                    }
                }
            }
        }

        if (!$valid_emails) {
            $this->mail['__reply_to'] !== false;
            return null;
        }

        $reply_to = array(
            'name' => reset($names),
            'email' => reset($valid_emails)
        );
        $reply_to['full'] = $reply_to['name'] . ' <' . $reply_to['email'] . '>';

        return $this->mail['__reply_to'] = $reply_to;
    }

    protected function isValidEmail($email)
    {
        $email = (string)$email;
        $email = trim($email);
        if (strlen($email) <= 0) {
            return false;
        }
        $validator = new waEmailValidator();
        return $validator->isValid($email);
    }

    public function getAttachments()
    {
        if (isset($this->mail['__attachments'])) {
            return $this->mail['__attachments'];
        }

        $attachments = array();

        $mail_dir = dirname($this->mail['source_path']);
        if (isset($this->mail['attachments']) && $this->mail['attachments']) {
            foreach ($this->mail['attachments'] as $a) {

                $file_path = $mail_dir . '/files/' . $a['file'];
                $file_name = ifempty($a['name'], $a['file']);
                $inline = false;

                if (isset($a['content-disposition'])) {
                    if (is_array($a['content-disposition'])) {
                        $a['content-disposition'] = implode(' ', $a['content-disposition']);
                    }

                    $disposition = explode(';', $a['content-disposition']);
                    $disposition = array_map(function ($el) {
                        return trim($el);
                    }, $disposition);

                    if ($disposition[0] === 'inline') {
                        $inline = true;
                        if (isset($disposition[1])) {
                            if (substr($disposition[1], 0, 10) === 'filename="') {
                                $file_name = substr($disposition[1], 10, -1);
                            } else {
                                $content_id = $a['content-id'];
                                $content_id = explode('@', $content_id, 2);
                                $file_name = $content_id[0];
                            }
                        }
                    }
                }

                if (file_exists($file_path)) {
                    $a['name'] = $file_name;
                    $a['inline'] = $inline;
                    $a['path'] = $file_path;
                    $a['request_file'] = new waRequestFile(array(
                        'name' => $a['name'],
                        'type' => $a['type'],
                        'size' => filesize($a['path']),
                        'tmp_name' => $a['path'],
                        'error' => UPLOAD_ERR_OK
                    ), true);
                    $attachments[] = $a;
                }
            }
        }
        return $this->mail['__attachments'] = $attachments;
    }


    /**
     * @return string
     */
    public function getSourcePath()
    {
        return $this->mail['source_path'];
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return (array)ifset($this->mail['headers']);
    }

    /**
     * @return array
     */
    public function getCopies()
    {
        if (isset($this->mail['__copies'])) {
            return $this->mail['__copies'];
        }
        $copies = $this->workupRecipients((array)ifset($this->mail['headers']['cc']));
        foreach ($copies as &$copy) {
            $copy['type'] = crmMessageRecipientsModel::TYPE_CC;
        }
        unset($copy);
        return $mail['__copies'] = $copies;
    }

    /**
     * @return array
     */
    public function getBlindCopies()
    {
        if (isset($this->mail['__blind_copies'])) {
            return $this->mail['__blind_copies'];
        }

        $bcc = ifset($this->mail['headers']['bcc']);
        if (is_scalar($bcc)) {
            $bcc = (string)$bcc;
            $copies = (array)waMailDecode::parseAddress($bcc);
        } elseif (is_array($bcc)) {
            $copies = $bcc;
        } else {
            $copies = array();
        }

        $copies = $this->workupRecipients($copies);
        foreach ($copies as &$copy) {
            $copy['type'] = crmMessageRecipientsModel::TYPE_BCC;
        }
        unset($copy);
        return $mail['__blind_copies'] = $copies;
    }

    public function getEnvelopeTo()
    {
        if (isset($this->mail['__envelope_to'])) {
            return $this->mail['__envelope_to'];
        }
        $ets = (string)ifset($this->mail['headers']['envelope-to']);
        $ets = (array)waMailDecode::parseAddress($ets);
        $ets = $this->workupRecipients($ets);
        foreach ($ets as &$et) {
            $et['type'] = crmMessageRecipientsModel::TYPE_BCC;
        }
        unset($et);
        return $mail['__envelope_to'] = $ets;
    }

    protected function workupRecipients($recipients)
    {
        $_recipients = array();
        foreach ($recipients as $recipient) {
            $email = is_array($recipient) && isset($recipient['email']) ? (string)$recipient['email'] : '';
            if (!$this->isValidEmail($email)) {
                continue;
            }
            $recipient['clean_email'] = $email;
            $recipient['suffix'] = '';
            $recipient['full_suffix'] = '';
            if (preg_match('/\+(\d+)@/', $email, $match)) {
                $recipient['suffix'] = $match[1];
                $recipient['full_suffix'] = '+' . $match[1];
                $email = preg_replace('/\+(\d+)@/', '@', $email);
                $recipient['clean_email'] = $email;
            }
            $_recipients[] = $recipient;
        }

        return $_recipients;
    }
}
