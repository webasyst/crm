<?php

class crmMessageRecipientsModel extends crmModel
{
    const TYPE_TO = 'TO';
    const TYPE_CC = 'CC';
    const TYPE_BCC = 'BCC';
    const TYPE_FROM = 'FROM';

    protected $table = 'crm_message_recipients';

    /**
     * @param $message_id
     * @param array $email_recipient [email, name, contact_id]
     * @param string $type self::TYPE_*
     */
    public function setEmailRecipient($message_id, $email_recipient, $type = self::TYPE_CC)
    {
        $this->setEmailRecipients($message_id, array($email_recipient), $type);
    }

    /**
     * @param int $message_id
     * @param array $email_recipients [ [email, name, contact_id]* ]
     * @param string $type self::TYPE_*
     */
    public function setEmailRecipients($message_id, $email_recipients, $type = self::TYPE_CC)
    {
        foreach ($email_recipients as &$email_recipient) {
            $email_recipient['destination'] = $email_recipient['email'];
        }
        unset($email_recipient);
        $this->setRecipients($message_id, $email_recipients, $type);
    }

    /**
     * @param $message_id
     * @param array $message_recipient [destination, name, contact_id]
     * @param string $type self::TYPE_*
     */
    public function setRecipient($message_id, $message_recipient, $type = self::TYPE_CC)
    {
        $this->setRecipients($message_id, array($message_recipient), $type);
    }

    /**
     * @param int $message_id
     * @param array $message_recipients [ [destination, name, contact_id]* ]
     * @param string $type self::TYPE_*
     */
    public function setRecipients($message_id, $message_recipients, $type = self::TYPE_CC)
    {
        $message_id = (int)$message_id;
        if ($message_id <= 0) {
            return;
        }

        foreach ($message_recipients as $recipient) {
            $recipient['type'] = ifset($recipient['type']);
            if (!$this->isAllowedType($recipient['type'])) {
                $recipient['type'] = $type;
            }
            $name = (string)ifset($recipient['name']);
            $insert = array(
                'message_id'  => $message_id,
                'destination' => $recipient['destination'],
                'type'        => $recipient['type'],
                'name'        => strlen($name) > 0 ? $name : null,
                'contact_id'  => isset($recipient['contact_id']) ? (int)$recipient['contact_id'] : null
            );
            $this->insert($insert, 2);
        }
    }

    /**
     * @param int $message_id
     * @param string $type self::TYPE_*
     * @return array
     */
    public function getRecipients($message_id, $type = null)
    {
        $message_id = (int) $message_id;
        if ($message_id <= 0) {
            return array();
        }

        if($type) {
            $type = "AND type = '{$type}'";
        }

        $sql = "SELECT *
                FROM {$this->table}
                WHERE message_id=?
                  {$type}
                GROUP BY destination";

        $result = $this->query($sql, $message_id)->fetchAll('destination');
        if (!$result) {
            return array();
        }

        return $result;
    }

    /**
     * @param array $message_ids
     * @return array
     */
    public function getRecipientsByMessages($message_ids, $type = null, $key = null)
    {
        $condition = '';
        if ($type) {
            $condition = "AND type='".$this->escape($type)."'";
        }
        $sql = "SELECT *
                FROM {$this->table}
                WHERE message_id IN('".join("','", $this->escape($message_ids))."') $condition";

        $result = $this->query($sql)->fetchAll($key);
        if (!$result) {
            return array();
        }
        return $result;
    }

    public function getAllRecipientsIds()
    {
        $ids = $this->query("SELECT `contact_id` FROM {$this->table} WHERE `contact_id` > 0 GROUP BY `contact_id`")->fetchAll('contact_id', true);
        $ids = array_keys($ids);
        return $ids;
    }

    protected function isAllowedType($type)
    {
        return $this->isAllowedConstValue('TYPE_', $type);
    }

}
