<?php

class crmNoteAddMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $content = (string) ifempty($_json, 'content', '');
        $contact_id = (int) ifempty($_json, 'contact_id', 0);
        $deal_id = (int) ifempty($_json, 'deal_id', 0);
        $cnt_dl_id = ($deal_id > 0 ? $deal_id * -1 : $contact_id);

        if (empty($content)) {
            throw new waAPIException('required_param', 'Required parameter is missing: content', 400);
        } else if (empty($deal_id) && empty($contact_id)) {
            throw new waAPIException('required_param', 'Required parameter is missing: deal_id or contact_id', 400);
        } else if ($cnt_dl_id === 0) {
            throw new waAPIException('not_found', 'Deal or contact not found', 404);
        } else if (!empty($deal_id) && !$this->getDealModel()->getById($deal_id)) {
            throw new waAPIException('not_found', 'Deal not found', 404);
        } else if (!empty($contact_id) && !$this->getContactModel()->getById($contact_id)) {
            throw new waAPIException('not_found', 'Contact not found', 404);
        } else if (!$this->getCrmRights()->contactOrDeal($cnt_dl_id)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $note = [
            'content'            => $content,
            'contact_id'         => $cnt_dl_id,
            'create_datetime'    => date('Y-m-d H:i:s'),
            'creator_contact_id' => wa()->getUser()->getId(),
        ];
        $action = 'note_add';
        $note_id = $this->getNoteModel()->insert($note);
        $this->getLogModel()->log($action, $cnt_dl_id, $note_id);
        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        $log_model->add($action, ['note_id' => $note_id]);
        wa('crm');

        $this->http_status_code = 201;
        $this->response = ['id' => $note_id];
    }
}
