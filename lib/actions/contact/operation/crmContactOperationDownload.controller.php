<?php

class crmContactOperationDownloadController extends crmContactOperationProcessController
{
    public function execute()
    {
        $name = basename(waRequest::get('file'));
        $file = wa()->getTempPath('contact/'.$name, 'crm');
        if (file_exists($file)) {
            waFiles::readFile($file, 'exported_contacts.csv');
        } else {
            throw new waException('File not found.', 404);
        }
    }
}
