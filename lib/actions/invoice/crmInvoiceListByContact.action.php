<?php

/**
 * HTML contents for Invoices contact profile tab.
 */
class crmInvoiceListByContactAction extends crmDealListAction
{
    public function execute()
    {
        $contact_id = waRequest::get('id', null, waRequest::TYPE_INT);
        if (!$contact_id) {
            throw new waException(_w('Contact not found'));
        }

        $manage_invoices_right = wa()->getUser()->getRights('crm', 'manage_invoices');

        $can_manage_invoices = $manage_invoices_right >= 2 || ($manage_invoices_right == 1 && wa()->getUser()->getId() == $contact_id);

        if (!$can_manage_invoices) {
            throw new waRightsException();
        }

        $collection = new crmContactsCollection('company/'.(int)$contact_id, array(
            'check_rights' => true,
        ));
        $employee_ids = array_keys($collection->getContacts('id') + array($contact_id => 1));

        $im = new crmInvoiceModel();
        $invoices = $im->getList(array(
            'contact_id'   => $employee_ids,
            'check_rights' => false,
            'sort'         => 'i.id',
            'order'        => 'desc',
        ));
        $creator_ids = array();
        foreach ($invoices as $i) {
            $creator_ids[$i['creator_contact_id']] = 1;
        }
        $collection = new crmContactsCollection('/id/'.join(',', array_keys($creator_ids)), array(
            'check_rights' => true,
        ));
        $creators = $collection->getContacts(null, 0, count($creator_ids));
        foreach ($creators as &$c) {
            $c = new waContact($c);
        }
        $this->view->assign(array(
            'invoices'   => $invoices,
            'contact_id' => $contact_id,
            'creators'   => $creators,
        ));
    }
}
