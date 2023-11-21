<?php

class crmMessageDeleteController extends crmJsonController
{
    public function execute()
    {
        $rights = new crmRights();
        if (!$rights->isAdmin()) {
            $this->accessDenied();
        }

        $id = waRequest::post('id', null, waRequest::TYPE_INT);
        $mm = new crmMessageModel();
        if ($id) {
            $message = $mm->getById($id);
        }

        if (empty($message)) {
            throw new waException(_w('Message not found'), 404);
        }

        $mm->delete($id);
    }
}