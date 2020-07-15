<?php

class crmContactResponsibleIdAction extends crmContactsAction
{
    protected $id;

    protected function afterExecute()
    {
        $contact = new waContact($this->getResponsibleId());

        $this->view->assign(array(
            'title' => _w('Responsible').": ".$contact['name'],
            'responsible' => $contact,
        ));
    }

    protected function getResponsibleId()
    {
        if ($this->id !== null) {
            return $this->id;
        }
        $this->id = (int) $this->getParameter('id');
        if ($this->id <= 0) {
            $this->notFound();
        }
        return $this->id;
    }

    protected function getHash()
    {
        $id = $this->getResponsibleId();
        return 'search/crm_user_id='.$id;
    }
}