<?php

class crmFileAddMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $file = $this->getFileContent($_json);
        $file_name = (string) ifempty($_json, 'file_name', 0);
        $file_name = trim($file_name);
        $deal_id = (int) ifempty($_json, 'deal_id', 0);
        $contact_id = (int) ifempty($_json, 'contact_id', 0);

        if (empty($file)) {
            throw new waAPIException('empty_file', 'Required parameter is missing: file', 400);
        } else if (empty($deal_id) && empty($contact_id)) {
            throw new waAPIException('required_param', 'Required parameter is missing: deal_id or contact_id', 400);
        } else if (
            (empty($deal_id) && empty($contact_id))
            || (!empty($deal_id) && $deal_id < 1)
            || (!empty($contact_id) && $contact_id < 1)
        ) {
            throw new waAPIException('not_found', 'Deal or contact not found', 404);
        } else if (empty($file_name)) {
            throw new waAPIException('empty_file_name', 'Required parameter is missing: file_name', 400);
        } else if (
            in_array($file_name, ['.', '..'])
            || !preg_match('#^[^:*?"<>|/\\\\]+$#', $file_name)
        ) {
            throw new waAPIException('invalid_file_name', 'Invalid file name', 400);
        }
        if ($deal_id) {
            $cnt_dl_id = $deal_id * -1;
            $deal = $this->getDealModel()->getDeal($deal_id);
            if ($deal === null) {
                throw new waAPIException('invalid', 'Unknown deal', 400);
            }
        } else {
            $cnt_dl_id = $contact_id;
            $contact = $this->getContactModel()->getById($contact_id);
            if ($contact === null) {
                throw new waAPIException('invalid', 'Unknown crm contact', 400);
            }
        }
        if (!$this->getCrmRights()->contactOrDeal($cnt_dl_id)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        /** download to temp directory */
        $name = md5(uniqid(__METHOD__));
        $temp_path = wa('crm')->getTempPath('files');
        waFiles::create($temp_path, true);
        $tmp_name = $temp_path."/$name";
        $n = file_put_contents($tmp_name, $file);
        if (!$n) {
            throw new waAPIException('server_error', 'Can\'t save the file', 500);
        }
        try {
            $file_size = (int) filesize($tmp_name);
        } catch (Exception $ex) {
            $file_size = 0;
        }
        $request_file = new waRequestFile([
            'name'     => $file_name,
            'type'     => '',
            'size'     => $file_size,
            'tmp_name' => $tmp_name,
            'error'    => 0
        ], true);

        /** add to private directory */
        $file_model = new crmFileModel();
        $file_id = $file_model->add(['contact_id' => $cnt_dl_id], $request_file);
        $_date = date('Y-m-d H:i:s');
        if ($file_id) {
            $clm = new crmLogModel();
            $clm->log(
                'file_add',
                $cnt_dl_id,
                $file_id,
                null,
                $request_file->name
            );
            $this->response = [
                'id'   => $file_id,
                'name' => $request_file->name,
                'ext'  => $request_file->extension,
                'size' => $file_size,
                'comment' => '',
                'create_datetime' => $this->formatDatetimeToISO8601($_date),
            ];
        } else {
            throw new waAPIException('failed', 'Failed to upload file', 500);
        }
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
}
