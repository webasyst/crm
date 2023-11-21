<?php

class crmEmployeeListByContactAction extends crmContactsAction
{
    protected $hash = null;

    public function execute()
    {
        $contact_id = waRequest::get('id', null, waRequest::TYPE_INT);
        if (!$contact_id) {
            throw new waException(_w('Contact not found'));
        }
        $contacts = array();
        $cm = new waContactModel();
        if ($employees = $cm->select('id')->where('company_contact_id = '.$contact_id)->fetchAll('id', true)) {
            $this->hash = '/id/'.join(',', array_keys($employees));
            $contacts = $this->getCollection()->getContacts($this->getFields(), $this->getOffset(), $this->getLimit());
            $contacts = $this->workupContacts($contacts);
        }
        $this->view->assign(array(
            'contacts' => $contacts,
        ));
    }

    protected function getHash()
    {
        return $this->hash;
    }
}
