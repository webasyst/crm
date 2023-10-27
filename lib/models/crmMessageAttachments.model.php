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

    public function getFilesByMessages($message_ids)
    {
        $result = [];
        if (!is_array($message_ids) || empty($message_ids)) {
            return $result;
        }
        $file_ids = $this->getByField('message_id', $message_ids, 'file_id');
        $files = $this->getFileModel()->getFiles(array_keys($file_ids));
        foreach ($file_ids as $_file_id => $_data) {
            if (!empty($files[$_file_id])) {
                $result[$_data['message_id']][$_file_id] = $files[$_file_id];
            }
        }

        return $result;
    }
}
