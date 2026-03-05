<?php

class crmNotificationInvoice extends crmNotification
{
    /**
     * @var array
     */
    protected $invoice;

    static protected $LOG_FILE = 'crm/notification_invoice_worker.log';

    protected function obtainInvoice($invoice)
    {
        if (wa_is_int($invoice)) {
            $id = (int)$invoice;
            if ($id <= 0) {
                return null;
            }
            $invoice = self::getInvoiceModel()->getInvoice($id);
        }
        if (!$invoice) {
            return null;
        }
        if (!isset($invoice['params'])) {
            $invoice['params'] = self::getInvoiceModel()->getInvoiceParams($invoice['id']);
        }
        if (!isset($invoice['items'])) {
            $invoice['items'] = self::getInvoiceModel()->getInvoiceItems($invoice['id']);
        }
        if (!isset($invoice['__company'])) {
            $invoice['__company'] = self::getCompanyModel()->getById($invoice['company_id']);
        }
        if (!isset($invoice['__customer'])) {
            $invoice['__customer'] = new crmContact($invoice['contact_id']);
        }
        return $invoice;
    }

    public function getInvoice()
    {
        if ($this->invoice !== null) {
            return $this->invoice === false ? null : $this->invoice;
        }
        if (isset($this->options['invoice'])) {
            $invoice = $this->obtainInvoice($this->options['invoice']);
        } else {
            $invoice = $this->obtainInvoice(ifset($this->options['invoice_id']));
        }
        if (!$invoice) {
            $this->invoice = false;
            return null;
        }
        return $this->invoice = $invoice;
    }

    /**
     * @param int|array $invoice
     */
    public function setInvoice($invoice)
    {
        $old_invoice = $this->invoice;
        $invoice = $this->obtainInvoice($invoice);
        if (!$invoice) {
            $this->invoice = false;
        } else {
            $this->invoice = $invoice;
        }
        $new_invoice = $this->getInvoice();

        // reset dependencies
        if ($old_invoice !== $new_invoice) {
            $this->vars = null;
        }
    }

    protected function getVars()
    {
        if ($this->vars !== null) {
            return $this->vars;
        }

        $this->vars = array();

        $customer = $this->getCustomer();
        $this->vars = array(
            'customer' => $customer
        );

        $invoice = $this->getInvoice();
        if ($invoice) {
            $company_id = ifset($invoice['company_id'], 0);
            $cm = new crmCompanyModel();
            $template_id = $cm->select('template_id')->where("`id` = {$company_id}")->fetchField('template_id');
            $cpm = new crmCompanyParamsModel();
            $company_params = $cpm->getParams($invoice['company_id'], $template_id);

            $link = null;
            if (!empty($company_params['domain'])) {
                $domain = $company_params['domain'];
                $domains = wa()->getRouting()->getByApp('crm');
                if (!empty($domains[$domain])) {
                    $link = self::getLink($invoice, $domain);
                }
            }
            if (is_null($link)) {
                $link = self::getLink($invoice);
            }

            $company = ifset($invoice['__company'], []);

            // unset special fields begin with '__'
            foreach ($invoice as $field => $value) {
                if (substr($field, 0, 2) === '__') {
                    unset($invoice[$field]);
                }
            }

            $this->vars = array_merge($this->vars, array(
                'invoice' => $invoice,
                'company' => $company,
                'link'    => $link,
            ));
        }

        return $this->vars;
    }

    protected static function getLink($invoice, $domain = null)
    {
        return wa()->getRouteUrl(
            'crm/frontend/invoice',
            array('hash' => crmHelper::getInvoiceHash($invoice)),
            true,
            $domain
        );
    }

    /**
     * @return array
     */
    public static function getVarsForInvoice()
    {
        $vars = array(
            '$invoice'       => _w('An array containing information about the invoice'),
            '$invoice.items' => _w('An array containing information about invoice items'),
        );

        foreach (self::getInvoiceModel()->getMetadata() as $field_id => $field) {
            $vars["\$invoice.{$field_id}"] = sprintf(_w('Field %s of invoice'), $field_id);
        }

        return $vars;
    }

    /**
     * @param array $options
     *   - $options['invoice_id']
     *   - $options['invoice']
     * @return bool
     * @throws waDbException
     * @throws waException
     */
    public function send($options = array())
    {
        $old_customer = false;
        $old_invoice = false;

        if (isset($options['invoice_id'])) {
            $old_invoice = $this->getInvoice();
            $this->setInvoice($options['invoice_id']);
        } elseif (isset($options['invoice'])) {
            $old_invoice = $this->getInvoice();
            $this->setInvoice($options['invoice']);
        }

        $invoice = $this->getInvoice();
        if ($invoice) {
            // Not send notification about invoice if notification has company and this company not the same as in invoice
            $notification_company = $this->getCompany();
            if ($notification_company && $invoice['__company'] && $notification_company['id'] != $invoice['__company']['id']) {
                return false;
            }
        }

        if (isset($options['customer_id'])) {
            $old_customer = $this->getCustomer();
            $this->setCustomer($options['customer_id']);
        } elseif (isset($options['customer'])) {
            $old_customer = $this->getCustomer();
            $this->setCustomer($options['customer']);
        }

        $customer = $this->getCustomer();
        if (!$customer && $invoice) {
            $old_customer = $this->getCustomer();
            $this->setCustomer($invoice['__customer']);
        }

        $res = $this->sendNotification();

        if ($old_customer !== false) {
            $this->setCustomer($old_customer);
        }

        if ($old_invoice !== false) {
            $this->setInvoice($old_invoice);
        }

        return $res;
    }

    protected function sendNotification()
    {
        if ($this->notification && $this->notification['status'] != 1) {
            return false;
        }

        $custom_address = $recipient = null;
        $this->recipient_type = $this->notification['recipient'];

        if ($this->recipient_type == 'client') {
            $recipient = $this->getCustomer();
        } elseif ($this->recipient_type == 'responsible') {
            $recipient = $this->getResponsible($this->invoice['contact_id']);
        } else {
            $custom_address = $this->recipient_type;
        }

        $transport = $this->getTransport();

        if (in_array($transport, [crmNotificationModel::TRANSPORT_EMAIL, crmNotificationModel::TRANSPORT_SMS]) && !$recipient && !$custom_address) {
            return true;
        }

        if ($transport === crmNotificationModel::TRANSPORT_EMAIL) {
            $res = $this->sendNotificationByEmail($recipient, $custom_address);
        } elseif ($transport === crmNotificationModel::TRANSPORT_SMS) {
            $res = $this->sendNotificationBySMS($recipient, $custom_address);
        } elseif ($transport === crmNotificationModel::TRANSPORT_HTTP) {
            $res = $this->sendHttp();
        } elseif ($transport === crmNotificationModel::TRANSPORT_REMINDER) {
            if (ifset($this->notification['reminder_user_type']) === 'responsible') {
                $user_contact_id = null;
                if (strpos($this->notification['event'], 'customer.') === 0) {
                    $user_contact_id = $this->getCustomer()->get('crm_user_id');
                } else {
                    if (!empty($this->invoice['contact_id'])) {
                        $contact = new waContact($this->invoice['contact_id']);
                        $user_contact_id = $contact->get('crm_user_id');
                    }
                    if (empty($user_contact_id) && !empty($this->invoice['creator_contact_id']) && (new waContact($this->invoice['creator_contact_id']))->get('is_user') == 1) {
                        $user_contact_id = $this->invoice['creator_contact_id'];
                    }
                }
                if (!empty($user_contact_id)) {
                    $res = $this->createReminder(ifset($this->invoice['contact_id'], 0), $user_contact_id);
                }
            } else {
                $res = $this->createReminder(ifset($this->invoice['contact_id'], 0));
            }
        } else {
            $res = false;
        }

        return $res;
    }

    protected function sendNotificationByEmail($recipient, $custom_address)
    {
        $email = null;
        if ($custom_address) {
            $email = $custom_address;
            $subjectName = null;
        } elseif ($recipient) {
            $email = $recipient->get('email', 'default');
            $subjectName = $recipient->getName();
        }

        if (!$email) {
            return false;
        }

        $body = $this->getBody(true);
        $subject = $this->getSubject(true);
        $from_address = waMail::getDefaultFrom();
        $from_name = null;

        //check sender type
        if ($this->notification['sender'] !== 'system') {
            $arrSender = preg_split("/\|/", $this->notification['sender']);
            $from_address = $arrSender[0];
            if (isset($arrSender[1])) {
                $from_name = $arrSender[1];
            }
        }

        $res = false;

        try {
            $m = new waMailMessage($subject, $body);
            $m->setFrom($from_address, $from_name);
            $m->setTo($email, $subjectName);

            if ($this->notification['sender'] !== 'system') {
                if ($m->getHeaders()->has('Sender')) {
                    $m->getHeaders()->get('Sender')->setValue(waMail::getDefaultFrom());
                } else {
                    $m->getHeaders()->addMailboxHeader('Sender', waMail::getDefaultFrom());
                }
            }

            $res = (bool)$m->send();

        } catch (Exception $e) {

        }

        if (waSystemConfig::isDebug()) {
            waLog::log("Email to user $email \"{$subject}\" sent", self::$LOG_FILE);
        }

        if (!$res) {
            return false;
        }

        if ($this->recipient_type == 'client') {
            $msg = array(
                'body'    => $body,
                'subject' => $subject,
                'from'    => $from_address,
                'to'      => $email
            );
            if (!empty($this->invoice['deal_id'])) {
                $msg['deal_id'] = $this->invoice['deal_id'];
            }
            $this->saveMessage($msg);

        }
        return true;
    }

    protected function sendNotificationBySMS($customer, $custom_address)
    {
        if ($custom_address) {
            $phone = $custom_address;
        } else {
            $phone = $customer->get('phone', 'default');
        }

        if (!$phone) {
            return false;
        }

        $text = $this->getBody(true);

        $sms = new crmSMS();
        if ($sms->send($phone, $text, $this->getSmsSender()) <= 0) {
            return false;
        }

        if (waSystemConfig::isDebug()) {
            waLog::log("sms to number {$phone} and text {$text} sent", self::$LOG_FILE);
        }

        if ($this->recipient_type == 'client') {
            $msg = array(
                'body'      => $text,
                'to'        => $phone,
                'transport' => 'SMS',
            );
            if (!empty($this->invoice['deal_id'])) {
                $msg['deal_id'] = $this->invoice['deal_id'];
            }
            $this->saveMessage($msg);
        }
        return true;
    }

    public static function getAllVars()
    {
        $all_vars = array();

        $spaces_vars = array();
        $spaces = self::getEventSpaces();
        foreach ((array)$spaces as $space) {
            switch ($space) {
                case 'invoice':
                    $spaces_vars[$space] = array_merge(self::getVarsForInvoice(), crmHelper::getVarsForContact(), self::getVarsForCompany());
                    break;
                case 'customer':
                    $spaces_vars[$space] = crmHelper::getVarsForContact();
                    break;
                default:
                    $spaces_vars[$space] = array();
                    break;
            }
        }

        foreach (self::getEventTypes() as $type) {
            $space = self::getSpaceOfEventType($type);
            $vars = (array)ifset($spaces_vars[$space]);
            $all_vars[$type] = $vars;
        }
        return $all_vars;
    }

    /**
     * @return array
     */
    public static function getVarsForCompany()
    {
        $vars = array();
        $company_list = array('name', 'phone', 'address', 'logo_url');

        foreach ($company_list as $field) {
            $vars["\$company.{$field}"] = sprintf(_w('Field %s of company'), $field);
        }

        //Get template params for 'crm_template_param'
        $invoice_template_id = waRequest::request('invoice_template_id', 0, 'int');
        if ($invoice_template_id) {
            $tpm = new crmTemplatesParamsModel();
            $params = $tpm->getParamsByTemplates($invoice_template_id);

            foreach ($params as $param) {
                $vars["\$company.invoice_options.{$param['code']}"] = $param['name'];
            }
        }
        return $vars;
    }

    /**
     * @return crmInvoiceModel
     */
    protected static function getInvoiceModel()
    {
        return !empty(self::$models['im']) ? self::$models['im'] : (self::$models['im'] = new crmInvoiceModel());
    }
}
