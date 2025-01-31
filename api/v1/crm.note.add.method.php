<?php

class crmNoteAddMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $content = (string) ifempty($_json, 'content', '');
        $contact_id = (int) ifempty($_json, 'contact_id', 0);
        $deal_id = (int) ifempty($_json, 'deal_id', 0);
        $attachments = ifempty($_json, 'attachments', []);

        $cnt_dl_id = ($deal_id > 0 ? $deal_id * -1 : $contact_id);

        if (empty($content)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'content'), 400);
        } else if (empty($deal_id) && empty($contact_id)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: %s.', sprintf_wp('“%s” or “%s”', 'deal_id', 'contact_id')), 400);
        } else if ($cnt_dl_id === 0) {
            throw new waAPIException('not_found', _w('Deal or contact not found.'), 404);
        } else if (!empty($deal_id) && !$this->getDealModel()->getById($deal_id)) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        } else if (!empty($contact_id) && !$this->getContactModel()->getById($contact_id)) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        } else if (!$this->getCrmRights()->contactOrDeal($cnt_dl_id)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $attachments = array_map(function($a) {
            return $this->toTmpFile($a);
        }, $attachments);
        $attachments = array_filter($attachments);

        $note = [
            'content'            => $content,
            'contact_id'         => $cnt_dl_id,
            'create_datetime'    => date('Y-m-d H:i:s'),
            'creator_contact_id' => wa()->getUser()->getId(),
        ];
        $action = 'note_add';
        $note_id = $this->getNoteModel()->insert($note);
        $this->getLogModel()->log($action, $cnt_dl_id, $note_id);
        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        $log_model->add($action, ['note_id' => $note_id]);
        wa('crm');

        foreach ($attachments as $attachment) {
            $this->saveFile($attachment, $cnt_dl_id, $note_id);
        }

        $this->http_status_code = 201;
        $this->response = ['id' => $note_id];
    }

    private function getFileContent($data)
    {
        $file_str = ifempty($data, 'file', null);
        if (empty($file_str)) {
            return null;
        }
        if (substr($file_str, 0, strlen('data:')) === 'data:') {
            $_parts = explode(',', $file_str);
            $file_str = end($_parts);
        }
        return base64_decode($file_str);
    }

    private function toTmpFile($data)
    {
        $file = $this->getFileContent($data);
        if (empty($file)) {
            return null;
        }
        $file_name = ifempty($data, 'file_name', null);
        $file_name = trim($file_name);
        if (empty($file_name)) {
            throw new waAPIException('empty_file_name', sprintf_wp('Missing required parameter: “%s”.', 'file_name'), 400);
        } else if (
            in_array($file_name, ['.', '..'])
            || !preg_match('#^[^:*?"<>|/\\\\]+$#', $file_name)
        ) {
            throw new waAPIException('invalid_file_name', _w('Invalid file name.'), 400);
        }

        /** download to temp directory */
        $name = md5(uniqid(__METHOD__));
        $temp_path = wa('crm')->getTempPath('files');
        waFiles::create($temp_path, true);
        $tmp_name = $temp_path."/$name";
        $n = file_put_contents($tmp_name, $file);
        if (!$n) {
            throw new waAPIException('server_error', _w('File could not be saved.'), 500);
        }
        try {
            $file_size = (int) filesize($tmp_name);
        } catch (Exception $ex) {
            $file_size = 0;
        }
        return new waRequestFile([
            'name'     => $file_name,
            'type'     => '',
            'size'     => $file_size,
            'tmp_name' => $tmp_name,
            'error'    => 0
        ], true);
    }

    private function saveFile(waRequestFile $file, $cnt_dl_id, $note_id)
    {
        $file_model = $this->getFileModel();
        $file_id = (int) $file_model->add([
            'contact_id' => $cnt_dl_id,
            'source_type' => crmFileModel::SOURCE_TYPE_NOTE,
        ], $file);

        $attachments_model = $this->getNoteAttachmentsModel();
        $attachments_model->insert([
            'note_id' => $note_id,
            'file_id' => $file_id
        ]);
        return $file_id;
    }
}
