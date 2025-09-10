<?php

class crmFrontendInvoiceAction extends crmFrontendViewAction
{
    protected $invoice = array();
    protected $payment_id = null;

    public function execute()
    {
        $hash = waRequest::param('hash', waRequest::post('hash', null, waRequest::TYPE_STRING_TRIM), waRequest::TYPE_STRING_TRIM);
        if (!$hash) {
            throw new waException(_w('Invoice not found'), 404);
        }
        $im = new crmInvoiceModel();
        $iim = new crmInvoiceItemsModel();
        $ipm = new crmInvoiceParamsModel();
        $cm = new crmCompanyModel();

        $id = intval(substr($hash, 16, -16));
        if (!$id) {
            throw new waException(_w('Invoice not found'), 404);
        }
        $this->invoice = $im->getInvoiceWithCompany($id);
        if (!$this->invoice || $hash != crmHelper::getInvoiceHash($this->invoice)) {
            throw new waException(_w('Invoice not found'), 404);
        }
        $invoices = (array)wa()->getStorage()->get('crm_frontend_invoices');
        $invoices[$id] = 1;

        wa()->getStorage()->set('crm_frontend_invoices', $invoices);
        wa()->getStorage()->set('crm/invoice_id', $id);

        $available_states = array('PENDING', 'PAID', 'PROCESSING', 'DRAFT', 'ARCHIVED');
        if (!in_array($this->invoice['state_id'], $available_states)) {
            throw new waRightsException();
        }
        $this->invoice['items'] = $iim->getByField('invoice_id', $id, true);
        $this->invoice['contact'] = null;
        $this->invoice['params'] = $ipm->getParams($id);
        $template = null;
        $style_version = 2;
        if (!empty($this->invoice['company']['template_id'])) {
            $template_record = (new crmTemplatesModel)->getById($this->invoice['company']['template_id']);
            if (!empty($template_record)) {
                $template = $template_record['content'];
                $style_version = $template_record['style_version'];
            }
        }

        try {
            $this->invoice['contact'] = new waContact($this->invoice['contact_id']);
            $this->invoice['contact']->getName();
        } catch (waException $e) {
            $this->invoice['contact'] = new waContact();
        }

        if ($this->invoice['state_id'] == 'PENDING') {
            if (!waRequest::post('hash')) {
                $this->getPayments();
            } else {
                $this->pay();
            }
        }
        if (!empty($this->invoice['company']['logo'])) {
            $this->invoice['company']['logo_url'] = wa()->getDataUrl(
                'logos/'.$this->invoice['company']['id'].'.'.$this->invoice['company']['logo'],
                true,
                'crm'
            );
        }
        $this->invoice['comment'] = crmHtmlSanitizer::work($this->invoice['comment']);
        $this->invoice['tax_name'] = htmlspecialchars($this->invoice['tax_name']);

        $this->invoice['subtotal'] = 0;
        foreach ($this->invoice['items'] as &$i) {
            $i['amount'] = $i['price'] * $i['quantity'];
            $i['tax_amount'] = $i['amount'] * $i['tax_percent'] / 100;
            $this->invoice['subtotal'] += $i['amount'];
        }
        unset($i);

        $template_render = new crmTemplatesRender(array(
            'invoice_id' => $id,
            'template' => $template,
            'hash'     => $hash,
            'invoice'  => $this->invoice,
            'style_version' => $style_version < 2 ? '' : '_v'.$style_version,
        ) + $this->view->getVars());

        $this->view->assign(array(
            'hash'     => $hash,
            'invoice'  => $this->invoice,
            'customer' => $this->invoice['contact'],
            'company'  => $this->invoice['company'],
            'html'     => $template_render->getRenderedTemplate(),
            'style_version' => $style_version < 2 ? '' : '_v'.$style_version,
        ));
    }

    private function getPayments()
    {
        $pm = new crmPaymentModel();

        $methods = $pm->select('*')->where(
            'status=1 AND company_id='.(int)$this->invoice['company_id']
        )->order('sort')->fetchAll('id'); //listPlugins();
        $m = new crmCurrencyModel();
        $currencies = $m->getAll('code');

        $selected = null;

        $plugins = $allowed_methods = array();
        foreach ($methods as $key => $m) {
            $method_id = $m['id'];
            try {
                $plugin = crmPayment::getPlugin($m['plugin'], $m['company_id']);
                $plugin_info = $plugin->info($m['plugin']);
                $methods[$key]['icon'] = $plugin_info['icon'];

                $custom_fields = $this->getCustomFields($method_id, $plugin);

                $custom_html = '';
                foreach ($custom_fields as $c) {
                    $custom_html .= '<div class="wa-field">'.$c.'</div>';
                }
                $methods[$key]['custom_html'] = $custom_html;

                $allowed_currencies = $plugin->allowedCurrency();
                if ($allowed_currencies !== true) {
                    $allowed_currencies = (array)$allowed_currencies;
                    if (!array_intersect($allowed_currencies, array_keys($currencies))) {
                        continue;
                    }
                    if (!in_array($this->invoice['currency_id'], $allowed_currencies)) {
                        continue;
                    }
                }
                $allowed_methods[$key] = $methods[$key];

                $plugins[$key] = $m;
                if (!$selected) {
                    $selected = $method_id;
                }
            } catch (waException $ex) {
                waLog::log($ex->getMessage(), 'crm/checkout.error.log');
            }
        }

        $this->view->assign('payment_methods', $allowed_methods);
        $this->view->assign('payment_id', $selected);
    }

    protected function getCustomFields($id, waPayment $plugin)
    {
        $contact = $this->getContact();
        $order_params = !empty($this->invoice['params']) ? $this->invoice['params'] : array();
        /*
        $order_params = array();
        foreach ($params as $k => $v) {
            $order_params[$k] = $v; // 'payment_params_'
        }
        */
        $order = new waOrder(array(
            'contact'    => $contact,
            'contact_id' => $contact ? $contact->getId() : null,
            'params'     => $order_params
        ));
        $custom_fields = $plugin->customFields($order);
        if (!$custom_fields) {
            return $custom_fields;
        }
        $params = array();
        $params['namespace'] = 'payment_'.$id;
        $params['title_wrapper'] = '%s';
        $params['description_wrapper'] = '<br><span class="hint">%s</span>';
        $params['control_wrapper'] = '<div class="wa-name">%s</div><div class="wa-value">%s %s</div>';

        $prefix = $params['namespace'].'_';
        $controls = array();
        foreach ($custom_fields as $name => $row) {
            $row = array_merge($row, $params);
            if (isset($order_params[$prefix.$name])) {
                $row['value'] = $order_params[$prefix.$name];
            }
            $controls[$name] = waHtmlControl::getControl($row['control_type'], $name, $row);
        }
        return $controls;
    }

    protected function pay()
    {
        $payment_id = waRequest::post('payment_id', null, waRequest::TYPE_INT);
        $payment = '';
        if ($payment_id) {
            try {
                /**
                 * @var waPayment $plugin
                 */
                $plugin = crmPayment::getPluginById($payment_id);
                $post_data = waRequest::post(null, array(), waRequest::TYPE_ARRAY_INT);
                $payment = $plugin->payment(
                    $post_data + array('app_payment' => true), // 'customer_data' => $this->invoice['company'],
                    crmPayment::getOrderData($this->invoice, $plugin),
                    true
                );
                if (!empty($post_data['payment_'.$payment_id]) && is_array($post_data['payment_'.$payment_id])) {
                    $delete_fields = $params = array();
                    $ipm = new crmInvoiceParamsModel();
                    $prefix = 'payment_'.$payment_id.'_';
                    foreach ($post_data['payment_'.$payment_id] as $name => $value) {
                        $delete_fields[$ipm->escape($prefix.$name)] = 1;
                        $params[] = array(
                            'invoice_id' => $this->invoice['id'],
                            'name'       => $prefix.$name,
                            'value'      => $value
                        );
                    }
                    $ipm->exec("DELETE FROM {$ipm->getTableName()} WHERE invoice_id = "
                        .(int)$this->invoice['id']." AND name IN('".join("','", array_keys($delete_fields))."')");
                    $ipm->multipleInsert($params);
                }
            } catch (waException $ex) {
                $payment = $ex->getMessage();
            }
        }
        if (isset($payment)) {
            $this->view->assign('payment', $payment);
        }
    }

    /**
     * @return waContact
     */
    protected function getContact()
    {
        $contact = new waContact($this->invoice['contact_id']);
        return $contact ? $contact : new waContact();
    }
}
