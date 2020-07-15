<?php
/**
 * Part of invoice editor. Creates new contact.
 */
class crmInvoiceContactAddSaveController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }

        $data = $this->getData();
        $id = $this->saveContact($data);
        if (!empty($this->errors['contact'])) {
            return;
        }

        $c = new waContact($id);
        $this->response = array(
            'id'   => $id,
            'name' => $c->getName(),
        );
    }

    protected function getId()
    {
        return (int)$this->getRequest()->request('id');
    }

    protected function getData()
    {
        $data = $this->getRequest()->post('data');
        $data = (array)ifset($data);
        unset($data['id']);
        return $data;
    }

    protected function saveContact($data)
    {
        $controller = new crmContactSaveController(array('data' => $data));
        $controller->execute();
        $res = $controller->getExecuteResult();
        if ($res['errors']) {
            $this->errors['contact'] = $res['errors'];
            return null;
        }
        return $res['response']['contact']['id'];
    }
}