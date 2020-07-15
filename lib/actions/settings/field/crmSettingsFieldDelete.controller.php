<?php

class crmSettingsFieldDeleteController extends crmJsonController
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

        $constructor = new crmFieldConstructor();
        if ($constructor->isFieldSystem($id)) {
            $this->accessDenied('Unable to delete protected system field.');
        }
        $constructor->deleteField($id);

        $this->response = array(
            'done' => true
        );
    }

    protected function getId()
    {
        return trim((string) $this->getRequest()->request('id'));
    }
}
