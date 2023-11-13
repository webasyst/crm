<?php

class crmFileDeleteMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_DELETE;

    public function execute()
    {
        $file_id = (int) $this->get('id', true);
        if ($file_id < 1) {
            throw new waAPIException('not_found', 'File not found', 404);
        }

        $file_model = $this->getFileModel();
        $file = $file_model->getById($file_id);
        if (!$file) {
            throw new waAPIException('not_found', 'File not found', 404);
        } else if (!$this->getCrmRights()->contactOrDeal($file['contact_id'])) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $file_model->delete($file['id']);
        $action = 'file_delete';
        $lm = new crmLogModel();
        $lm->log(
            $action,
            $file['contact_id'],
            $file['id'],
            $file['name']
        );

        $this->http_status_code = 204;
        $this->response = null;
    }
}
