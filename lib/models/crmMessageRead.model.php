<?php

class crmMessageReadModel extends crmModel
{
    protected $table = 'crm_message_read';

    /**
     * @param int|array[]int $message_id
     * @param int|array[]int $contact_id
     */
    public function setRead($message_id, $contact_id)
    {
        $message_ids = crmHelper::toIntArray($message_id);
        $message_ids = crmHelper::dropNotPositive($message_ids);
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);

        $values = array();
        foreach ($message_ids as $message_id) {
            foreach ($contact_ids as $contact_id) {
                $values[] = "({$message_id}, {$contact_id})";
            }
        }

        if (!$values) {
            return;
        }

        $values = join(', ', $values);
        $sql = "INSERT IGNORE INTO {$this->table} (`message_id`, `contact_id`) VALUES {$values}";
        $this->exec($sql);
    }

    /**
     * @param int|array[]int $message_id
     * @param int|array[]int $contact_id
     */
    public function setUnread($message_id, $contact_id)
    {
        $message_ids = crmHelper::toIntArray($message_id);
        $message_ids = crmHelper::dropNotPositive($message_ids);
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);

        if (!$message_ids || !$contact_ids) {
            return;
        }

        $this->deleteByField(array(
            'message_id' => $message_ids,
            'contact_id' => $contact_ids
        ));
    }

    /**
     * @param int|array[]int $message_id
     * @param int $contact_id
     * @param bool $normalize - if TRUE return message_id => bool map FOR all message_ids (there is not holes in data)
     * @return array
     */
    public function getReadStatus($message_id, $contact_id, $normalize = false)
    {
        $message_ids = crmHelper::toIntArray($message_id);
        $message_ids = crmHelper::dropNotPositive($message_ids);
        if (!$message_ids) {
            return array();
        }

        $contact_id = (int) $contact_id;

        $sql = "SELECT message_id, count(*) as `read`
                    FROM {$this->table}
                    WHERE contact_id=?
                      AND message_id IN (?)
                    GROUP BY message_id";
        $statuses = $this->query($sql, $contact_id, $message_ids)->fetchAll('message_id', $normalize);

        if ($normalize) {
            foreach ($message_ids as $message_id) {
                if (!isset($statuses[$message_id])) {
                    $statuses[$message_id] = false;
                } else {
                    $statuses[$message_id] = (bool)$statuses[$message_id];
                }
            }
        }

        return $statuses;
    }

    public function setReadConversation($conversation_id, $contact_id = null)
    {
        if ($contact_id == null) {
            $contact_id = wa()->getUser()->getId();
        }

        $select_sql = "SELECT m.id AS message_id, :contact_id AS contact_id
                        FROM `crm_message` m
                            LEFT JOIN `crm_message_read` r ON m.id = r.message_id AND r.contact_id = :contact_id  
                        WHERE m.conversation_id = :conversation_id AND r.message_id IS NULL";

        $insert_sql = "INSERT IGNORE INTO `crm_message_read` (`message_id`, `contact_id`) 
                            {$select_sql}";

        $this->exec($insert_sql, [
            'contact_id' => $contact_id,
            'conversation_id' => $conversation_id
        ]);
    }
}
