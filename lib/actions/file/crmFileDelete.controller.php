<?php

class crmFileDeleteController extends crmJsonController
{
    public function execute()
    {
        $file = $this->getFile();

        $this->getFileModel()->delete($file['id']);

        $action = 'file_delete';
        $this->logAction($action, array('note_id' => $file['id']));
        $lm = new crmLogModel();
        $lm->log(
            $action,
            $file['contact_id'],
            $file['id'],
            $file['name']
        );
    }

    /**
     * @throws waException
     * @throws waRightsException
     * @return array
     */
    protected function getFile()
    {
        $id = (int)waRequest::post('id');
        if ($id <= 0) {
            $this->notFound();
        }
        $file = $this->getFileModel()->getById($id);
        if (!$file) {
            $this->notFound();
        }
        if (!$this->getCrmRights()->contactOrDeal($file['contact_id'])) {
            $this->accessDenied();
        }
        return $file;
    }
}
