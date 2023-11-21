<?php

class crmFileDownloadMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    public function execute()
    {
        $file_id = $this->get('id', true);
        if ($file_id < 1) {
            throw new waAPIException('not_found', _w('File not found'), 404);
        }

        $file = $this->getFileModel()->getFile($file_id);
        if (!$file) {
            throw new waAPIException('not_found', _w('File not found'), 404);
        }
        if (!$this->getCrmRights()->contactOrDeal($file['contact_id'])) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        wa()->getResponse()->addHeader('Cache-Control', 'private, no-transform');
        waFiles::readFile($file['path'], $file['name']);
    }
}
