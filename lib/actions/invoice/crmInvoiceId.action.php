<?php

/**
 * HTML for a single invoice page.
 */
class crmInvoiceIdAction extends crmInvoiceViewAction
{
    protected $invoice = array();
    protected $emulate_action = null;

    public function execute()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }

        // Invoice ID may come from routing, or from parent class
        $invoice_id = waRequest::param('id', null, waRequest::TYPE_INT);
        if (!$invoice_id) {
            if ($this->invoice_id) {
                $invoice_id = (int)$this->invoice_id;
            } else {
                $this->emulate_action = new crmInvoiceNewAction();
                return;
            }
        }

        // Get invoice data
        $im = new crmInvoiceModel();
        $this->invoice = $im->getInvoiceWithCompany($invoice_id);

        if (wa()->getUser()->getRights('crm', 'manage_invoices') < 2 && $this->invoice['creator_contact_id'] != wa()->getUser()->getId()) {
            throw new waRightsException();
        }

        // Check access rights
        $contact = $this->newContact($this->invoice['contact_id']);
        if (!$this->getCrmRights()->contact($contact)) {
            $this->accessDenied();
        }

        $cm = new crmCompanyModel();
        $companies = $cm->getAll('id');

        $transactions = self::getTransactions($this->invoice);

        $curm = new crmCurrencyModel();
        $currencies = $curm->getAll('code');

        $deal = null;
        $deal_access_denied = false;
        if ($this->invoice['deal_id']) {
            $dm = new crmDealModel();
            $deal = $dm->getById($this->invoice['deal_id']);
            if (!$this->getCrmRights()->deal($deal)) {
                $deal_access_denied = true;
                $deal = null;
            }
        }

        $show_url = true;
        if (in_array($this->invoice['state_id'], array('DRAFT', 'ARCHIVED', 'REFUNDED'))) {
            $show_url = false;
        }

        if ($this->invoice["creator_contact_id"]) {
            $this->invoice["creator_contact"] = $this->newContact($this->invoice["creator_contact_id"]);
        }

        // Parameters for events
        $params = array('invoice' => &$this->invoice);
        $backend_invoice = wa('crm')->event('backend_invoice', $params);

        $public_url = null;
        if (!empty($params['invoice']['company']['invoice_options']['domain'])) {
            $domain = $params['invoice']['company']['invoice_options']['domain'];
            $domains = wa()->getRouting()->getByApp($this->getAppId());
            if (!empty($domains[$domain])) {
                $public_url = self::getPublicUrl($this->invoice, $domain);
            }
        }
        if (is_null($public_url)) {
            $public_url = self::getPublicUrl($this->invoice);
        }

        $currency = waCurrency::getInfo($this->invoice['currency_id']);

        $this->invoice['subtotal'] = 0;
        foreach ($this->invoice['items'] as &$i) {
            $i['amount'] = $i['price'] * $i['quantity'];
            if ($i['tax_type'] == 'APPEND') {
                $i['tax_amount'] = $i['amount'] * $i['tax_percent'] / 100;
            } else {
                $i['tax_amount'] = ($i['amount'] / (100 + $i['tax_percent'])) * $i['tax_percent'];
            }
            if (!empty($currency['precision'])) {
                $i['tax_amount'] = round($i['tax_amount'], $currency['precision']);
            }
            $this->invoice['subtotal'] += $i['amount'];
        }
        unset($i);

        $this->view->assign(array(
            'invoice'                => $this->invoice,
            'public_url'             => $public_url,
            'show_url'               => $show_url,
            'transactions'           => $transactions,
            'plugins'                => crmPayment::getList(),
            'is_cancel_available'    => self::isCancellable($this->invoice, $transactions),
            'companies'              => $companies,
            'currencies'             => $currencies,
            'contact'                => $contact,
            'deal'                   => $deal,
            'deal_access_denied'     => $deal_access_denied,
            'invoice_id'             => $invoice_id,
            'backend_invoice'        => $backend_invoice,
            'customer'               => $this->invoice['contact_id'] ? (new waContact($this->invoice['contact_id'])) : null,
            'company'                => ifset($this->invoice, 'company', null),
            'root_path'              => $this->getConfig()->getRootPath().DIRECTORY_SEPARATOR
        ));

        wa('crm')->getConfig()->setLastVisitedUrl('invoice/');
    }

    protected static function getPublicUrl($invoice, $domain = null)
    {
        return waIdna::dec(wa()->getRouteUrl(
            'crm/frontend/invoice',
            array('hash' => crmHelper::getInvoiceHash($invoice)),
            true,
            $domain
        ));
    }

    protected static function isCancellable($invoice, $transactions)
    {
        if ($invoice['state_id'] == 'PAID' && strtotime($invoice['payment_datetime']) > time() - 60 * 60) {
            foreach ($transactions as $t) {
                if ($t['state'] == waPayment::STATE_CAPTURED) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    protected static function getTransactions($invoice)
    {
        $tm = new waTransactionModel();
        return $tm->select('*')
                  ->where("app_id='crm'")
                  ->where("order_id=?", (int)$invoice['id'])
                  ->where("type IN (?)", array(array(waPayment::OPERATION_AUTH_ONLY, waPayment::OPERATION_AUTH_CAPTURE)))
                  ->order('id ASC')
                  ->fetchAll('id');
    }

    public function display($clear_assign = true)
    {
        $this->view->cache($this->cache_time);
        if ($this->cache_time && $this->isCached()) {
            return $this->view->fetch($this->getTemplate(), $this->cache_id);
        } else {
            if (!$this->cache_time && $this->cache_id) {
                $this->view->clearCache($this->getTemplate(), $this->cache_id);
            }
            $this->preExecute();
            $this->execute();
            if ($this->emulate_action) {
                $result = $this->emulate_action->display($clear_assign);
            } else {
                $result = $this->view->fetch($this->getTemplate(), $this->cache_id);
            }
            if ($clear_assign) {
                $this->view->clearAllAssign();
            }
            return $result;
        }
    }

    /**
     * Get contact object (even if contact not exists)
     * BUT please don't save it
     *
     * @param $contact_id
     * @return waContact
     * @throws waException
     */
    protected function newContact($contact_id)
    {
        $contact = new waContact($contact_id);
        if (!$contact->exists()) {
            $contact = new waContact();
            $contact['id'] = $contact_id;
            $contact['name'] = sprintf_wp("Contact with ID %s doesn't exist", $contact_id);
        }
        return $contact;
    }
}
