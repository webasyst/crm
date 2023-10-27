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

        $file_list = [];
        if ($this->get('with_files')) {
            $file_list = $this->getFiles();
            $contact_ids = array_merge($contact_ids, array_unique(array_column($file_list, 'creator_contact_id')));
        }

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

        if ($this->get('with_files')) {
            $file_list = $this->prepareFileList($file_list, $contact_list);

            // Put files into notes if created at the same time by the same creator
            $note_list = array_map(function ($_note) use (&$file_list) {
                $note_dt = strtotime($_note['create_datetime']);
                $files = array_filter($file_list, function ($_file) use ($_note, $note_dt) {
                    $file_dt = strtotime($_file['create_datetime']);
                    return ($file_dt >= $note_dt && $file_dt - $note_dt < 20 
                            || $file_dt <= $note_dt && $note_dt -$file_dt < 2)
                        && ifset($_file, 'creator', 'id', null) == ifset($_note, 'creator', 'id', null);
                });
                if (!empty($files)) {
                    $file_list = array_filter($file_list, function ($_file) use ($files) {
                        return !in_array($_file['id'], array_column($files, 'id'));
                    });
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
                        ])
                    );
                }
                return $_note;
            }, $note_list);

            // Exclude files included into notes from the file list
            $extracted_files = array_column($note_list, 'files');
            $extracted_files = array_merge(...$extracted_files);
            $extracted_file_ids = array_column($extracted_files, 'id');
            $file_list = array_filter($file_list, function ($_file) use ($extracted_file_ids) {
                return !in_array($_file['id'], $extracted_file_ids);
            });

            // Convert file list to note list (with empty content and attached file)
            $file_list = array_map(function ($el) {
                return [
                    'id' => 0,
                    'create_datetime' => $el['create_datetime'],
                    'creator' => $el['creator'],
                    'content' => '',
                    'files' => $this->filterData(
                            [$el],
                            [
                                'id',
                                'name',
                                'create_datetime',
                                'size',
                                'ext',
                                'comment',
                                'url',
                            ])
                ];
            }, $file_list);

            // Union notes & files into one list 
            $note_list = array_merge($note_list, $file_list);
            array_multisort(array_column($note_list, 'create_datetime'), $note_list);
            $note_list = array_reverse($note_list);
        }

        $this->response = $note_list;
    }
}
