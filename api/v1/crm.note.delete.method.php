<?php

class crmNoteDeleteMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_DELETE;

    public function execute()
    {
        $note_id = (int) $this->get('id', true);
        if ($note_id < 1) {
            throw new waAPIException('not_found', _w('Note not found'), 404);
        }
        $note = $this->getNoteModel()->getById($note_id);
        if ($note === null) {
            throw new waAPIException('not_found', _w('Note not found'), 404);
        } else if (!$this->getCrmRights()->contactOrDeal($note['contact_id'])) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $this->getNoteModel()->deleteById($note_id);
        $file_ids = array_keys($this->getNoteAttachmentsModel()->getByField(['note_id' => $note_id], 'file_id'));
        if (!empty($file_ids)) {
            $this->getNoteAttachmentsModel()->deleteByField(['note_id' => $note_id]);
            $this->getFileModel()->delete($file_ids);
        }

        $action = 'note_delete';
        $this->getLogModel()->log($action, $note['contact_id']);
        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        $log_model->add($action, ['note_id' => $note_id]);
        wa('crm');

        $this->http_status_code = 204;
        $this->response = null;
    }
}
