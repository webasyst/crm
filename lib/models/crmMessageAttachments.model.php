<?php

class crmMessageAttachmentsModel extends crmModel
{
    protected $table = 'crm_message_attachments';

    /**
     * @param int $message_id
     * @return array
     */
    public function getFiles($message_id)
    {
        $message_id = (int) $message_id;
        if ($message_id <= 0) {
            return array();
        }
        $file_ids = $this->getByField('message_id', $message_id, 'file_id');
        $file_ids = array_keys($file_ids);
        return $this->getFileModel()->getFiles($file_ids);
    }
}
