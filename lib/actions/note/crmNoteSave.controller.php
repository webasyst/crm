<?php

class crmNoteSaveController extends crmJsonController
{
    public function execute()
    {
        $data = $this->getData();

        $errors = $this->validate($data);
        if ($errors) {
            $this->errors = $errors;
            return;
        }
        $id = $this->saveData($data);

        $this->response = array(
            'id' => $id
        );
    }

    protected function validate($data)
    {
        $errors = array();

        $required = array('content');
        foreach ($required as $r) {
            if (empty($data[$r])) {
                $errors[$r] = _w('This field is required');
            }
        }
        return $errors;
    }

    protected function saveData($data)
    {
        $nm = new crmNoteModel();

        $now = date('Y-m-d H:i:s');

        $lm = new crmLogModel();
        if (!$data['id']) {
            if (empty($data['contact_id'])) {
                throw new waException('Contant not found');
            }
            if (!$this->getCrmRights()->contactOrDeal($data['contact_id'])) {
                throw new waRightsException();
            }
            $note = array(
                'content'            => $data['content'],
                'create_datetime'    => $now,
                'creator_contact_id' => wa()->getUser()->getId(),
                'contact_id'         => $data['contact_id'],
            );
            $id = $nm->insert($note);

            $action = 'note_add';
            $lm->log($action, $note['contact_id'], $id);
        } else {
            $id = $data['id'];

            if (!($note = $nm->getById($id))) {
                throw new waException(_w('Note not found'));
            }
            if (!$this->getCrmRights()->contactOrDeal($note['contact_id'])) {
                throw new waRightsException();
            }
            $note['content'] = $data['content'];

            $nm->updateById($id, $note);

            $action = 'note_edit';
        }
        $this->logAction($action, array('note_id' => $id));

        return $id;
    }

    protected function getData()
    {
        $data = $this->getRequest()->post('data', array(), waRequest::TYPE_ARRAY_TRIM);

        $data['id'] = (int)ifset($data['id']);
        $data['content'] = ifset($data['content']);
        $data['contact_id'] = intval(!empty($data['deal_id']) ? $data['deal_id'] * -1 : (!empty($data['contact_id']) ? $data['contact_id'] : 0));

        return $data;
    }
}
