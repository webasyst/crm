<?php

/**
 * Class crmMessageMarkController
 */
abstract class crmMessageOperationController extends crmJsonController
{
    /**
     * @return int[]
     */
    protected function getMessageIds()
    {
        $message_ids = $this->getRequest()->post('message_ids');
        $message_ids = waUtils::toIntArray($message_ids);
        $message_ids = waUtils::dropNotPositive($message_ids);
        return $message_ids;
    }

    protected function getLastMessageIds($conversation_ids)
    {
        if (!$conversation_ids) {
            return array();
        }
        return $this->getConversationModel()->getLastMessageIds($conversation_ids);
    }

    /**
     * @return int[]
     */
    protected function getConversationIds()
    {
        $conversation_ids = $this->getRequest()->post('conversation_ids');
        $conversation_ids = waUtils::toIntArray($conversation_ids);
        $conversation_ids = waUtils::dropNotPositive($conversation_ids);
        return $conversation_ids;
    }

    protected function isCheckMode()
    {
        return (bool)$this->getRequest()->post('check');
    }

    /**
     * Text about how much conversations be affected by the action due to insufficient access rights.
     * @param int $after_count - size of list of conversations after sieving by rights
     * @param int $before_count - size of list of conversations before sieving by rights
     * @return mixed|string
     */
    protected function getCheckText($after_count, $before_count)
    {
        $diff = $before_count - $after_count;
        if ($diff <= 0) {
            return '';
        }
        return _w('%d conversation will not be affected by the action due to insufficient access rights',
                    '%d conversations will not be affected by the action due to insufficient access rights',
                        $diff);
    }
}
