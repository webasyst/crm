<?php
/**
 * Modify a reminder using data from inline editor form.
 */
class crmReminderSaveController extends crmJsonController
{
    public function execute()
    {
        $data = $this->getData();

        $errors = $this->validate($data);
        if ($errors) {
            $this->errors = $errors;
            return;
        }
        $data['user_contact_id'] = $data['user_contact_id'] ? $data['user_contact_id'] : wa()->getUser()->getId();

        $reminder_id = $this->saveData($data);

        $this->response = array(
            'id' => $reminder_id
        );
    }

    protected function validate($data)
    {
        $errors = array();

        if ($data['id']) {
            $rm = new crmReminderModel();
            if (!($reminder = $rm->getById($data['id']))) {
                throw new waException('Reminder not found');
            }
            if (!$this->getCrmRights()->reminderEditable($reminder)) {
                throw new waRightsException();
            }
        }
        $required = array('type', 'content', 'due_date', 'due_time', 'deal_id');
        $countEmpty = true;
        foreach ($required as $r) {
            if ($r === 'type') {
                if ($data[$r] !== "OTHER"){
                    $countEmpty = false;
                }
            }
            else {
                if (!empty($data[$r])) {
                    $countEmpty = false;
                }
            }
        }
        if ($countEmpty) {
            $errors['content'] = _w('At least one of these fields must be filled.');
        }

        if ($data['contact_id'] && $data['contact_id'] < 0) {
            $dm = new crmDealModel();
            if (!$dm->getById(abs($data['contact_id']))) {
                throw new waException(_w('Deal not found'));
            }
        }
        return $errors;
    }

    protected function saveData($data)
    {
        $rm = new crmReminderModel();
        $now = date('Y-m-d H:i:s');
        $types = crmConfig::getReminderType();
        $reminder = array(
            'update_datetime' => $now,
            'user_contact_id' => $data['user_contact_id'],
            'content'         => $data['content'],
            'contact_id'      => $data['contact_id'] ? $data['contact_id'] : null,
            'type'            => ifset($types[$data['type']]) ? $data['type'] : 'OTHER',
        );
        $dt = crmNaturalInput::matchDueDate($data['content']);

        if ($data['due_date']) {
            $reminder['due_date'] = date('Y-m-d', strtotime($data['due_date']));
        } else {
            $reminder['due_date'] = !empty($dt['due_date']) ? $dt['due_date'] : null;
        }
        if ($data['due_time']) {
            $reminder['due_datetime'] = waDateTime::parse('Y-m-d H:i:s', $reminder['due_date'].' '.$data['due_time'].':00');
        } else {
            $reminder['due_datetime'] = !empty($dt['due_datetime']) ? waDateTime::parse('Y-m-d H:i:s', $dt['due_datetime']) : null;
        }
        if (empty($reminder['due_date'])) {
            $reminder['due_date'] = null;
            $reminder['due_datetime'] = null;
        }
        $action = 'reminder_update';
        if (!$data['id']) {
            $action = 'reminder_add';
            $reminder['create_datetime'] = $now;
            $reminder['creator_contact_id'] = wa()->getUser()->getId();
            $id = $reminder['id'] = $rm->insert($reminder);

            if ($reminder['user_contact_id'] != wa()->getUser()->getId()) {
                crmReminder::sendNotification($reminder, array($reminder['user_contact_id']), 'reminder_new');
            }
        } else {
            $id = $data['id'];

            if (!($old_reminder = $rm->getById($id))) {
                throw new waException('Reminder not found');
            }
            if ($old_reminder['complete_datetime']) {
                $reminder = array(
                    'update_datetime' => $now,
                    'content'         => $data['content'],
                );
            } elseif ($reminder['due_datetime']) {
                $reminder['push_sent'] = 0;
            }
            $rm->updateById($id, $reminder);
            $reminder['contact_id'] = $old_reminder['contact_id'];
        }
        if (!empty($reminder['contact_id'])) {
            crmDeal::updateReminder($reminder['contact_id']);
        }
        $this->logAction($action, array('reminder_id' => $id));
        $lm = new crmLogModel();
        $lm->log($action, $reminder['contact_id'], $id);

        return $id;
    }

    protected function getData()
    {
        $data = $this->getRequest()->post('data', array(), waRequest::TYPE_ARRAY_TRIM);

        $data['id'] = (int)ifset($data['id']);
        $data['user_contact_id'] = (int)ifset($data['user_contact_id']);
        $data['due_date'] = ifset($data['due_date']);
        $data['due_time'] = ifset($data['due_time']);
        $data['content'] = ifset($data['content']);
        $data['contact_id'] = intval(!empty($data['deal_id']) ? $data['deal_id'] * -1 : (!empty($data['contact_id']) ? $data['contact_id'] : 0));
        $data['type'] = ifset($data['type']);

        return $data;
    }
}
