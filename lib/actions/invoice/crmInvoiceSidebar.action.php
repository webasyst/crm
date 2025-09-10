<?php

/**
 * Middle sidebar HTML. Used either as a part of InvoiceViewAction,
 * or separately for lazy loading or changing filters.
 */
class crmInvoiceSidebarAction extends waViewAction
{
    public $invoice_id = null;

    public function execute()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }

        // Get data from DB
        $im = new crmInvoiceModel();
        $list_params = $this->getListParams();
        $invoices = $im->getList($list_params);
        $this->addContactsToInvoices($invoices);

        // Indicates to crmInvoiceViewAction which invoice to show by default
        $this->invoice_id = waRequest::request('invoice_id', null, 'int');
        if (!$this->invoice_id) {
            reset($invoices);
            $this->invoice_id = key($invoices);
        }

        // Used to highlight new invoices
        $invoice_max_id = $im->select('MAX(id)')->fetchField();
        wa()->getResponse()->setCookie('invoice_max_id', $invoice_max_id, time() + 86400);

        $this->view->assign(array(
            'invoice_id'     => $this->invoice_id,
            'invoice_max_id' => $invoice_max_id,
            'list_params'    => $list_params,
            'invoices'       => $invoices,
        ));
    }

    protected function getListParams()
    {
        $list_params = array(
            'check_rights' => true,
            'offset'       => waRequest::post('offset', 0, waRequest::TYPE_INT),
            'limit'        => 30,
        );

        $sort = waRequest::get('sort', wa()->getUser()->getSettings(wa()->getApp(), 'invoice_sort'), waRequest::TYPE_STRING_TRIM);
        if (waRequest::get('sort')) {
            wa()->getUser()->setSettings(wa()->getApp(), 'invoice_sort', $sort);
        }
        if ($sort == 'newest') {
            $list_params['sort'] = "id";
            $list_params['order'] = "DESC";
        } elseif ($sort == 'updated') {
            $list_params['sort'] = "update_datetime";
            $list_params['order'] = "DESC";
        } else {
            if ($sort == 'paid') {
                $list_params['sort'] = "payment_datetime";
                $list_params['order'] = "DESC";
            } else {
                $list_params['order'] = null;
            }
        }
        $list_params['sort_filter'] = $sort;

        $state = waRequest::get('state', wa()->getUser()->getSettings(wa()->getApp(), 'invoice_state'), waRequest::TYPE_STRING_TRIM);
        if (waRequest::get('state')) {
            wa()->getUser()->setSettings(wa()->getApp(), 'invoice_state', $state);
        }
        if ($state && $state !== 'all') {
            $list_params['state_id'] = $state;
        }
        return $list_params;
    }


    function addContactsToInvoices(&$invoices)
    {
        $contact_ids = array();
        foreach ($invoices as &$i) {
            $contact_ids[$i['contact_id']] = 1;
        }
        unset($i);

        if ($contact_ids) {
            $collection = new waContactsCollection('/id/'.join(',', array_keys($contact_ids)));
            $contacts = $collection->getContacts(wa('crm')->getConfig()->getContactFields(), 0, count($contact_ids));
            foreach ($contacts as $c) {
                $contacts[$c['id']] = new waContact($c);
            }
            foreach ($invoices as &$i) {
                if (empty($i['contact_id'])) {
                    $i['contact'] = new waContact();
                    $i['contact']['name'] = _w('Client not specified.');
                } elseif (!empty($contacts[$i['contact_id']])) {
                    $i['contact'] = $contacts[$i['contact_id']];
                } else {
                    $i['contact'] = new waContact();
                    $i['contact']['name'] = sprintf_wp('deleted id=%d', $i['contact_id']);
                }
            }
            unset($i);
        }
    }
}
