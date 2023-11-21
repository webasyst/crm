<?php

class crmNoteDeleteController extends crmJsonController
{
    public function execute()
    {
        $id = $this->getRequest()->post('id', null, waRequest::TYPE_INT);

        $nm = new crmNoteModel();
        $note = $nm->getById($id);

        if (!$id || !$note) {
            throw new waException(_w('Note not found'));
        }
        if (!$this->getCrmRights()->contactOrDeal($note['contact_id'])) {
            throw new waRightsException();
        }
        $nm->deleteById($id);

        $action = 'note_delete';
        $this->logAction($action, array('note_id' => $id));
        $lm = new crmLogModel();
        $lm->log($action, $note['contact_id']);
    }
}
