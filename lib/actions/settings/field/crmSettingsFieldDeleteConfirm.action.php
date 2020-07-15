<?php

class crmSettingsFieldDeleteConfirmAction extends crmBackendViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $id = $this->getId();
        if (strlen($id) <= 0) {
            $this->notFound();
        }

        $hash = "/search/{$id}!=";
        $collection = new waContactsCollection($hash);
        $count = $collection->count();
        $field = waContactFields::get($id, 'all');
        $this->view->assign(array(
            'id' => $id,
            'name' => $field->getName(null, true),
            'count' => $count
        ));
    }

    protected function getId()
    {
        return trim((string) $this->getRequest()->request('id'));
    }
}