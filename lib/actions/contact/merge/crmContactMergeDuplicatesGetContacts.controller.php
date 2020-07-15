<?php

class crmContactMergeDuplicatesGetContactsController extends crmJsonController
{
    public function execute()
    {
        $field = $this->getField();
        $value = $this->getValue();

        $contacts = array();

        if (in_array($field, array('name', 'email', 'phone'))) {
            $q = "{$field}={$value}";
            $col = new crmContactsCollection("search/{$q}");
            $count = $col->count();
            $col->orderBy('create_datetime', 'DESC');
            $contacts = array_keys($col->getContacts('id', 0, $count));
            if (count($contacts) < 2) {
                return;
            }
            if ($this->isMasterSlaves()) {
                $this->response = array(
                    'master' => $contacts[0],
                    'slaves' => array_slice($contacts, 1)
                );
                return;
            }
        }

        $this->response['contacts'] = $contacts;
    }

    protected function getField()
    {
        return $this->getRequest()->request('field');
    }

    protected function getValue()
    {
        return $this->getRequest()->request('value');
    }

    protected function isMasterSlaves()
    {
        return !!$this->getRequest()->request('master_slaves');
    }
}