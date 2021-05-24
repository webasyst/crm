<?php

class crmNotificationDeal extends crmNotification
{
    /**
     * @var array
     */
    protected $deal;

    static protected $LOG_FILE = 'crm/notification_deal_worker.log';

    public static function getBackendUrl()
    {
        if (waRequest::server('HTTP_HOST')) {
            $url = wa()->getRootUrl(true).wa('crm')->getConfig()->getBackendUrl();
        } else {
            $app_settings_model = new waAppSettingsModel();
            $url = $app_settings_model->get('webasyst', 'url', '#').wa('crm')->getConfig()->getBackendUrl();
        }
        return $url;
    }

    protected function obtainDeal($deal)
    {
        if (!$deal || !is_array($deal) || empty($deal['contact_id'])) {
            return null;
        }

        $dm = new crmDealModel();

        // ensure correct structure of deal and default values
        $deal = array_merge($dm->getEmptyDeal(), $deal);

        if (!isset($deal['__customer'])) {
            $deal['__customer'] = new crmContact($deal['contact_id']);
        }
        if (!isset($deal['__responsible'])) {
            $deal['__responsible'] = new crmContact($deal['user_contact_id']);
        }
        if (!isset($deal['__company'])) {
            $deal['__company'] = $deal['__customer']['company_contact_id']
                ? self::getContactModel()->getById($deal['__customer']['company_contact_id']) : null;
        }

        $deal['url'] = self::getBackendUrl().'/crm/deal/'.$deal['id'].'/';

        $dsm = new crmFunnelStageModel();
        $stage = $dsm->getById($deal['stage_id']);
        $deal['stage'] = $stage;
        if (!empty($deal['before_stage_id'])) {
            $stage = $dsm->getById($deal['before_stage_id']);
            $deal['before_stage'] = $stage;
        }
        $dsm = new crmFunnelModel();
        $funnel = $dsm->getById($deal['funnel_id']);
        $deal['funnel'] = $funnel;
        if (!empty($deal['before_funnel_id'])) {
            $funnel = $dsm->getById($deal['before_funnel_id']);
            $deal['before_funnel'] = $funnel;
        }

        $deal['description_sanitized'] = crmHtmlSanitizer::work($deal['description']);

        return $deal;
    }

    public function getDeal()
    {
        if ($this->deal !== null) {
            return $this->deal === false ? null : $this->deal;
        }
        if (isset($this->options['deal'])) {
            $deal = $this->obtainDeal($this->options['deal']);
        }
        if (empty($deal)) {
            $this->deal = false;
            return null;
        }
        return $this->deal = $deal;
    }

    /**
     * @param int|array $deal
     */
    public function setDeal($deal)
    {
        $old_deal = $this->deal;
        $deal = $this->obtainDeal($deal);
        if (!$deal) {
            $this->deal = false;
        } else {
            $this->deal = $deal;
        }
        $new_deal = $this->getDeal();

        // reset dependencies
        if ($old_deal !== $new_deal) {
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

        $deal = $this->getDeal();

        if ($deal) {

            // unset special fields begin with '__'
            foreach ($deal as $field => $value) {
                if (substr($field, 0, 2) === '__') {
                    unset($deal[$field]);
                }
            }

            $this->vars = array_merge($this->vars, array(
                'deal'        => $deal,
                'responsible' => $this->deal['__responsible'],
                'company'     => $this->deal['__company'],
            ));
        }
        return $this->vars;
    }

    /**
     * @param array
     * @return bool
     */
    public function send($options = array())
    {
        $old_customer = false;
        $old_deal = false;

        $deal = isset($options['deal']) ? $options['deal'] : (isset($this->options['deal']) ? $this->options['deal'] : null);
        if ($deal) {
            $old_deal = $deal;
            $this->setDeal($deal);
        }
        $deal = $this->getDeal();

        if (isset($deal['contact_id'])) {
            $old_customer = $this->getCustomer();
            $this->setCustomer($deal['contact_id']);
        }
        $customer = $this->getCustomer();
        if (!$customer && $deal) {
            $old_customer = $this->getCustomer();
            $this->setCustomer($deal['__customer']);
        }

        $res = $this->sendNotification();

        if ($old_customer !== false) {
            $this->setCustomer($old_customer);
        }

        if ($old_deal !== false) {
            $this->setDeal($old_deal);
        }
        return $res;
    }

    /**
     * Replaces the recipient's address with the one that was specified when creating the test message
     * @param int|string $address
     * @return bool
     * @throws waException
     */
    public function sendTestNotification($address)
    {
        $old_address = $this->recipient_type;
        $this->notification['recipient'] = $address;
        try {
            $this->sendNotification();
        } catch (waException $e) {
            $this->recipient_type = $old_address;
            throw $e;
        }
        $this->recipient_type = $old_address;
        return true;
    }

    protected function sendNotification()
    {
        if ($this->notification && $this->notification['status'] != 1) {
            return false;
        }

        $custom_address = $recipient = null;
        $this->recipient_type = $this->notification['recipient'];

        // $responsible = $this->getResponsible($this->deal['user_contact_id']);
        $this->responsible = new waContact($this->deal['user_contact_id']);

        if ($this->recipient_type == 'client') {
            $recipient = $this->getCustomer();
        } elseif ($this->recipient_type == 'responsible') {
            $recipient = $this->responsible; // $responsible;
        } else {
            $custom_address = $this->recipient_type;
        }
        if (!$recipient && !$custom_address) {
            return true;
        }

        $transport = $this->getTransport();
        if ($transport === crmNotificationModel::TRANSPORT_EMAIL) {
            $res = $this->sendNotificationByEmail($recipient, $custom_address);
        } elseif ($transport === crmNotificationModel::TRANSPORT_SMS) {
            $res = $this->sendNotificationBySMS($recipient, $custom_address);
        } else {
            $res = false;
        }

        if (!$res) {
            return false;
        }
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
        $subject = $this->getSubject(true);
        $body = $this->getBody(true);

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
            $this->saveMessage($msg);
        }
        return true;
    }

    public static function getAllVars($include_deal_stage = false)
    {
        $all_vars = array();

        $spaces_vars = array();
        $spaces = self::getEventSpaces();
        foreach ((array)$spaces as $space) {
            switch ($space) {
                case 'deal':
                    $spaces_vars[$space] = array_merge(self::getVarsForDeal($include_deal_stage), crmHelper::getVarsForContact());
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
    public static function getVarsForDeal($include_deal_stage = false)
    {
        $vars = array(
            '$deal' => _w('An array containing information about the deal'),
        );

        $extra = array('stage.name', 'funnel.name');
        if ($include_deal_stage) {
            $extra += array('limit_hours', 'in_datetime', 'out_datetime');
        }
        $fields = self::getDealModel()->getMetadata();
        $fields = array_merge(array_keys($fields), $extra);

        foreach ($fields as $field_id) {
            $vars["\$deal.{$field_id}"] = sprintf(_w('Field %s of deal'), $field_id);
        }
        return $vars;
    }

    /**
     * @return crmInvoiceModel
     */
    protected static function getDealModel()
    {
        return !empty(self::$models['dm']) ? self::$models['dm'] : (self::$models['dm'] = new crmDealModel());
    }
}
