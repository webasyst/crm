<?php

class crmNoteAttachmentsModel extends crmModel
{
    protected $table = 'crm_note_attachments';

    /**
     * @param int $message_id
     * @return array
     */
    public function getFiles($note_id)
    {
        $note_id = (int) $note_id;
        if ($note_id <= 0) {
            return array();
        }
        $file_ids = $this->getByField('note_id', $note_id, 'file_id');
        $file_ids = array_keys($file_ids);
        return $this->getFileModel()->getFiles($file_ids);
    }

    public function getFilesByNotes($note_ids)
    {
        $result = [];
        if (!is_array($note_ids) || empty($note_ids)) {
            return $result;
        }
        $file_ids = $this->getByField('note_id', $note_ids, 'file_id');
        $files = $this->getFileModel()->getFiles(array_keys($file_ids));
        foreach ($file_ids as $_file_id => $_data) {
            if (!empty($files[$_file_id])) {
                $result[$_data['note_id']][$_file_id] = $files[$_file_id];
            }
        }

        return $result;
    }
}
