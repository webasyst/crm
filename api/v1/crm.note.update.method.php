<?php

class crmNoteUpdateMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_PUT;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $content = (string) ifempty($_json, 'content', '');
        $content = trim($content);
        $note_id = (int) $this->get('id', true);
        if ($note_id < 1) {
            throw new waAPIException('non_found', _w('Note not found'), 404);
        } else if (empty($content)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'content'), 400);
        }

        $cnm = $this->getNoteModel();
        $note = $cnm->getById($note_id);
        if ($note === null) {
            throw new waAPIException('non_found', _w('Note not found'), 404);
        }
        if (!$this->getCrmRights()->contactOrDeal($note['contact_id'])) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }
        $action = 'note_edit';
        $note['content'] = $content;
        $cnm->updateById($note_id, $note);

        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        $log_model->add($action, ['note_id' => $note_id]);
        wa('crm');

        $this->http_status_code = 204;
    }
}
