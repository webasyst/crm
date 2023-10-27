<?php

class crmDealExportDownloadController extends waViewController
{
    public function execute()
    {
        $name = basename(waRequest::get('file'));
        $file = wa()->getTempPath('deal/'.$name, 'crm');
        if (file_exists($file)) {
            waFiles::readFile($file, 'exported_deals.csv');
        } else {
            throw new waException('File not found.', 404);
        }
    }
}
