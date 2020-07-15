<?php

class crmFileDownloadEmlController extends waController
{
    public function execute()
    {
        $id = waRequest::get('id');
        $mm = new crmMessageModel();
        $message = $mm->getById($id);

        if (!$message || !$message['original']) {
            throw new waException(_w('File not found'), 404);
        }
        $crm_rights = new crmRights();
        if (!$crm_rights->contactOrDeal($message['contact_id'])) {
            throw new waRightsException();
        }

        $path = $mm->getEmailSourceFilePath($message['id']);
        $info = pathinfo($path);

        wa()->getResponse()->addHeader("Cache-Control", "private, no-transform");
        waFiles::readFile($path, $info['basename']);
    }
}
