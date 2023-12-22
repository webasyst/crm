<?php

class crmReminderAddMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;
    private $reminder_types = ['OTHER', 'MEETING', 'CALL', 'MESSAGE'];

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $content = (string) ifempty($_json, 'content', '');
        $content = trim($content);
        $contact_id = (int) ifempty($_json, 'contact_id', 0);
        $deal_id = (int) ifempty($_json, 'deal_id', 0);
        $user_id = (int) ifempty($_json, 'user_id', $this->getUser()->getId());
        $type = (string) ifempty($_json, 'type', reset($this->reminder_types));
        $due_date = (string) ifempty($_json, 'due_date', '');
        $due_datetime = (string) ifempty($_json, 'due_datetime', '');

        $this->validate($user_id, $content, $deal_id, $contact_id, $type, $due_date);
        $reminder_id = $this->saveData($user_id, $content, $deal_id, $contact_id, $type, $due_date, $due_datetime);

        $this->http_status_code = 201;
        $this->response = ['id' => $reminder_id];
    }

    private function validate($user_id, $content, $deal_id, $contact_id, $type, $due_date)
    {
        if ($user_id < 1) {
            throw new waAPIException('invalid_user', sprintf_wp('Invalid “%s” value.', 'user_id'), 400);
        }
        if (!in_array($type, $this->reminder_types)) {
            throw new waAPIException('invalid_type', _w('Invalid reminder type specified.'), 400);
        }
        if (empty($content) && $type === 'OTHER') {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: “%s”.', 'content'), 400);
        }
        if (!empty($due_date)) {
            $validate = new waDateIsoValidator();
            if (!$validate->isValid($due_date)) {
                $description = implode(', ', $validate->getErrors());
                throw new waAPIException('invalid_date', sprintf_wp('Invalid “due_date” value (ISO 8601 YYYY-MM-DD): %s', $description), 400);
            }
        }
        if ($user_id != $this->getUser()->getId()) {
            $user = $this->getContactModel()->getById($user_id);
            if (empty($user) || intval($user['is_user']) !== 1) {
                throw new waAPIException('invalid_user', sprintf_wp('Invalid “%s” value.', 'user_id'), 400);
            }
        }
        if ($deal_id) {
            if ($deal_id < 0) {
                throw new waAPIException('invalid_deal', _w('Invalid deal specified.'), 400);
            }
            $deal = $this->getDealModel()->getById($deal_id);
            if ($deal === null) {
                throw new waAPIException('invalid_deal', _w('Invalid deal specified.'), 400);
            }
            if (!$this->getCrmRights()->deal($deal)) {
                throw new waAPIException('forbidden', _w('Access denied'), 403);
            }
        } else if ($contact_id) {
            if ($contact_id < 0) {
                throw new waAPIException('invalid_contact', _w('Invalid contact specified.'), 400);
            }
            $contact = $this->getContactModel()->getById($contact_id);
            if ($contact === null) {
                throw new waAPIException('invalid_contact', _w('Invalid contact specified.'), 400);
            }
            if (!$this->getCrmRights()->contact($contact)) {
                throw new waAPIException('forbidden', _w('Access denied'), 403);
            }
        }
    }

    private function saveData($user_id, $content, $deal_id, $contact_id, $type, $due_date, $due_datetime)
    {
        $reminder = [
            'create_datetime'    => date('Y-m-d H:i:s'),
            'creator_contact_id' => wa()->getUser()->getId(),
            'due_datetime'       => null,
            'contact_id'         => null,
            'user_contact_id'    => $user_id,
            'content'            => $content,
            'type'               => $type,
        ];

        if (empty($due_date) && empty($due_datetime)) {
            $dt = crmNaturalInput::matchDueDate($content);
            $reminder['due_date'] = ifempty($dt, 'due_date', null);
            $reminder['due_datetime'] = ifempty($dt, 'due_datetime', null);
        } elseif (empty($due_datetime)) {
            $reminder['due_date'] = date('Y-m-d', strtotime($due_date));
        } else {
            $reminder['due_date'] = date('Y-m-d', strtotime($due_datetime));
            $reminder['due_datetime'] = date('Y-m-d H:i:s', strtotime($due_datetime));
        }

        if ($deal_id || $contact_id) {
            $reminder['contact_id'] = ($deal_id ? $deal_id * -1 : $contact_id);
        }
        $reminder['id'] = $this->getReminderModel()->insert($reminder);

        if ($reminder['user_contact_id'] != wa()->getUser()->getId()) {
            $contact = new waContact($reminder['user_contact_id']);
            if (!$contact->getSettings('crm', 'reminder_disable_assign')) {
                crmReminder::sendNotification($reminder, [$reminder['user_contact_id']], 'reminder_new');
            }
        }
        if (!empty($reminder['contact_id'])) {
            crmDeal::updateReminder($reminder['contact_id']);
        }

        $action = 'reminder_add';
        $this->getLogModel()->log($action, $reminder['contact_id'], $reminder['id']);
        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        $log_model->add($action, ['reminder_id' => $reminder['id']]);
        wa('crm');

        return $reminder['id'];
    }
}
