<?php

class crmFileDownloadController extends crmJsonController
{
    public function execute()
    {
        $file = $this->getFile();

        wa()->getResponse()->addHeader("Cache-Control", "private, no-transform");
        waFiles::readFile($file['path'], $file['name']);
    }

    /**
     * @throws waException
     * @throws waRightsException
     * @return array
     */
    protected function getFile()
    {
        $id = (int)$this->getRequest()->get('id');
        if ($id <= 0) {
            $this->notFound();
        }
        $file = $this->getFileModel()->getFile($id);
        if (!$file) {
            $this->notFound();
        }
        if (!$this->getCrmRights()->contactOrDeal($file['contact_id'])) {
            $this->accessDenied();
        }
        return $file;
    }
}
