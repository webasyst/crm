<?php

class crmInvoiceInfoMethod extends crmApiAbstractMethod
{

    public function execute()
    {
        $id = (int) $this->get('id', true);
        $userpic_size = waRequest::get('userpic_size', 32, waRequest::TYPE_INT);

        $cim = new crmInvoiceModel();
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        } elseif ($id < 1) {
            throw new waAPIException('not_found', _w('Invoice not found'), 404);
        } elseif (!$invoice = $cim->getInvoice($id)) {
            throw new waAPIException('not_found', _w('Invoice not found'), 404);
        }

        $items = $this->filterData($invoice['items'], [
            'id',
            'name',
            'price',
            'quantity',
            'tax_percent',
            'tax_type',
            'product_id'
        ], [
            'id'          => 'integer',
            'price'       => 'float',
            'quantity'    => 'float',
            'tax_percent' => 'float'
        ]);
        $invoice = $this->prepareInvoice($invoice);
        $contact_id = ifset($invoice, 'contact_id', 0);
        $creator_id = ifset($invoice, 'creator_contact_id', 0);
        $contacts_list = $this->getContactsMicrolist([$contact_id, $creator_id], ['id', 'name', 'userpic'], $userpic_size);

        foreach ($contacts_list as $_contact) {
            switch ($_contact['id']) {
                case $contact_id:
                    $invoice['contact'] = $_contact;
                    break;
                case $creator_id:
                    $invoice['creator'] = $_contact;
                    break;
            }
        }

        $this->response = $invoice + ['items' => $items];
    }
}
