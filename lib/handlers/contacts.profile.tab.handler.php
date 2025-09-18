<?php

class crmContactsProfileTabHandler extends waEventHandler
{
    public function execute(&$params)
    {
        if ($this->isOldContacts() || $this->isRequestCrm20()) {
            return;
        }

        $contact_id = (is_array($params) ? ifset($params, 'id', 0) : $params);

        $has_access_app = wa()->getUser()->getRights(wa()->getApp(), 'backend');

        $backend_url = wa()->getConfig()->getBackendUrl(true);

        $old_app = wa()->getApp();
        wa('crm', 1);

        $result = array();

        // Timeline (activity, history, log)
        if ($has_access_app) {
            $result[] = array(
                'id'    => 'history',
                'title' => _w('Timeline'), //"<i class=\"icon16 clock\" style='margin-left: 5px;'></i>" . _w('Timeline'),
                'url'   => $backend_url.'crm/?module=log&contact_id='.$contact_id,
                'count' => '',
            );
        }

        // Employees
        $employees = array();
        $c = new waContact($contact_id);
        if ($c['is_company']) {
            $cm = new waContactModel();
            $employees = $cm->select('id')->where('company_contact_id='.(int)$contact_id)->fetchAll('id', true);
        }
        $all_contacts = array_keys($employees + array($contact_id => 1));

        // Deals
        $crm_rights = wa()->getUser()->getRights('crm');
        $can_manage_deals = false;
        if ($crm_rights) {
            foreach ($crm_rights as $name => $value) {
                if (($name == 'backend' && $value >= 2) || stripos($name, 'funnel') !== false) {
                    $can_manage_deals = true;
                }
            }
        }
        if ($can_manage_deals) {
            $dm = new crmDealModel();
            $deals_count = $dm->countByParticipants($all_contacts);
            $result[] = array(
                'id'    => 'deals',
                'title' => _w('Deals'),
                'url'   => $backend_url.'crm/?module=deal&action=listByContact&id='.$contact_id,
                'count' => $deals_count,
            );
        }

        // Invoices
        $manage_invoices_right = wa()->getUser()->getRights('crm', 'manage_invoices');
        $can_manage_invoices = $manage_invoices_right >= 2 || ($manage_invoices_right == 1 && wa()->getUser()->getId() == $contact_id);

        if ($can_manage_invoices) {
            $im = new crmInvoiceModel();
            $invoices = $im->select('id')->where(
                "contact_id IN ('" . join("','", $im->escape($all_contacts)) . "')"
            )->fetchAll('id', true); //getByField('contact_id', $contact_id, true);
            $result[] = array(
                'id' => 'invoices',
                'title' => _w('Invoices'),
                'url' => $backend_url . 'crm/?module=invoice&action=listByContact&id=' . $contact_id,
                'count' => count($invoices),
            );
        }

        // Employees
        if ($employees) {
            $result[] = array(
                'id'    => 'employees',
                'title' => _w('Employees'),
                'url'   => $backend_url.'crm/?module=employee&action=listByContact&id='.$contact_id,
                'count' => count($employees),
            );
        }

        wa($old_app, 1);
        return ifempty($result, null);
    }

    protected function isOldContacts()
    {
        $is_old_contacts = waRequest::request('module', '', 'string') == 'contacts'
            && waRequest::request('action', '', 'string') == 'info'
            && wa()->appExists('contacts');
        if ($is_old_contacts) {
            $is_old_contacts = version_compare(wa()->getVersion('contacts'), '1.2.0') < 0;
        }
        return $is_old_contacts;
    }

    protected function isRequestCrm20()
    {
        if (wa()->getApp() === 'crm') {
            if (wa()->whichUI('crm') !== '1.3' || wa()->getEnv() === 'api') {
                return true;
            }
        }

        return false;
    }
}
