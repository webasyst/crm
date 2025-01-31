<?php

class crmNoteListMethod extends crmFileListMethod
{
    public function execute()
    {
        $this->validateParams();
        $note_list = $this->getNoteModel()
            ->select('*')
            ->where('contact_id = ?', $this->contact_id)
            ->order('create_datetime DESC')
            ->fetchAll();

        $contact_ids = array_unique(array_column($note_list, 'creator_contact_id'));
        $contact_list = $this->getContacts($contact_ids);

        $note_list = array_map(function ($_note) use ($contact_list) {
            $_note['creator'] = ifset($contact_list, $_note['creator_contact_id'], []);
            return $_note;
        }, $note_list);

        $note_list = $this->filterData(
            $note_list,
            [
                'id',
                'create_datetime',
                'creator',
                'content',
            ], [
                'id' => 'integer',
                'create_datetime' => 'datetime'
            ]
        );

        $file_list = $this->getNoteFiles();
        $note_list = array_map(function ($_note) use ($file_list) {
            $files = array_filter($file_list, function ($_file) use ($_note) {
                return $_file['note_id'] == $_note['id'];
            });
            if (!empty($files)) {
                $_note['files'] = array_values($this->filterData(
                    $files,
                    [
                        'id',
                        'name',
                        'create_datetime',
                        'size',
                        'ext',
                        'comment',
                        'url',
                        'thumb_url',
                    ], [
                        'id' => 'integer',
                        'create_datetime' => 'datetime',
                        'size' => 'integer'
                    ])
                );
            }
            return $_note;
        }, $note_list);

        $this->response = $note_list;
    }

    protected function getNoteFiles()
    {
        $file_list = $this->getFileModel()->query("SELECT f.*, a.note_id
            FROM crm_file f 
            INNER JOIN crm_note_attachments a ON a.file_id = f.id 
            WHERE f.contact_id = i:contact_id
            ORDER BY f.create_datetime DESC", 
            ['contact_id' => $this->contact_id])->fetchAll();
        
        if (empty($file_list)) {
            return [];
        }

        $thumb_size = waRequest::get('thumb_size', self::THUMB_SIZE, waRequest::TYPE_INT);
        $host_backend = rtrim(wa()->getConfig()->getHostUrl(), '/').wa()->getConfig()->getBackendUrl(true);
        return array_map(function ($_file) use ($host_backend, $thumb_size) {
            $_file['url'] = $host_backend.'crm/?module=file&action=download&id='.$_file['id'];
            if (in_array($_file['ext'], ['jpg', 'jpeg', 'png'])) {
                $_file['thumb_url'] = $host_backend.'crm/?module=file&action=download&id='.$_file['id'].'&thumb='.$thumb_size;
            }
            return $_file;
        }, $file_list);
    }
}
