<?php

class crmFileDeleteMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_DELETE;

    public function execute()
    {
        $file_id = (int) $this->get('id', true);
        if ($file_id < 1) {
            throw new waAPIException('not_found', _w('File not found'), 404);
        }

        $file_model = $this->getFileModel();
        $file = $file_model->getById($file_id);
        if (!$file) {
            throw new waAPIException('not_found', _w('File not found'), 404);
        } else if (!$this->getCrmRights()->contactOrDeal($file['contact_id'])) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $file_model->delete($file['id']);

        $message_link = $this->getMessageAttachmentsModel()->getByField(['file_id' => $file['id']]);
        if (!empty($message_link)) {
            $this->getMessageAttachmentsModel()->deleteByField(['file_id' => $file['id']]);
            $message_footer_param = $this->getMessageParamsModel()->getByField([
                'message_id' => $message_link['message_id'], 
                'name' => 'footer',
            ]);
            $message_footer_param = empty($message_footer_param['value']) ? '' : $message_footer_param['value'] . '<br>';
            $message_footer_param .= sprintf_wp('File <b>%s</b> was deleted by <b>%s</b>.', $file['name'], wa()->getUser()->getName());
            $this->getMessageParamsModel()->replace([
                'message_id' => $message_link['message_id'],
                'name' => 'footer',
                'value' => $message_footer_param,
            ]);
        }
        
        $this->getNoteAttachmentsModel()->deleteByField(['file_id' => $file['id']]);

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
