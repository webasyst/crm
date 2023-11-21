<?php

/**
 * Create a new reminder from plain text.
 *
 * @DEPRECATED
 */
class crmReminderAddController extends crmJsonController
{
    public function execute()
    {
        $user_id = waRequest::post('user_id', null, waRequest::TYPE_INT);
        $content = waRequest::post('content', null, waRequest::TYPE_STRING_TRIM);
        $deal_id = waRequest::post('deal_id', null, waRequest::TYPE_INT);
        $contact_id = waRequest::post('contact_id', null, waRequest::TYPE_INT);

        $errors = $this->validate($user_id, $content, $deal_id, $contact_id);
        if ($errors) {
            $this->errors = $errors;
            return;
        }
        $reminder_id = $this->saveData($user_id, $content, $deal_id, $contact_id);

        $this->response = array(
            'id' => $reminder_id
        );
    }

    protected function validate($user_id, $content, $deal_id, $contact_id)
    {
        if (!$user_id) {
            throw new waException('User not found.');
        }
        $errors = array();
        /*if (!$content) {
            $errors['content'] = _w('This field is required');
        }*/
        if ($deal_id) {
            $dm = new crmDealModel();
            $deal = $dm->getById($deal_id);
            if (!$deal_id) {
                throw new waException(_w('Deal not found'));
            }
            if (!$this->getCrmRights()->deal($deal)) {
                throw new waRightsException();
            }
        } elseif ($contact_id) {
            $c = new waContact($contact_id);
            if (!$this->getCrmRights()->contact($c)) {
                throw new waRightsException();
            }
        }
        return $errors;
    }

    protected function saveData($user_id, $content, $deal_id, $contact_id)
    {
        $rm = new crmReminderModel();

        $dt = crmNaturalInput::matchDueDate($content);

        $reminder = array(
            'create_datetime'    => date('Y-m-d H:i:s'),
            'creator_contact_id' => wa()->getUser()->getId(),
            'user_contact_id'    => $user_id,
            'content'            => $content,
            'type'               => 'OTHER',
        );
        //$reminder['content'] = !empty($content) ? $content : '';
        $reminder['due_date'] = !empty($dt['due_date']) ? $dt['due_date'] : date('Y-m-d', strtotime('+1 day'));
        $reminder['due_datetime'] = !empty($dt['due_datetime']) ? $dt['due_datetime'] : null;
        if ($deal_id || $contact_id) {
            $reminder['contact_id'] = $deal_id ? ($deal_id * -1) : $contact_id;
        }

        $reminder['id'] = $rm->insert($reminder);

        if ($reminder['user_contact_id'] != wa()->getUser()->getId()) {
            $c = new waContact($reminder['user_contact_id']);
            if (!$c->getSettings('crm', 'reminder_disable_assign')) {
                crmReminder::sendNotification($reminder, array($reminder['user_contact_id']), 'reminder_new');
            }
        }
        if (!empty($reminder['contact_id'])) {
            crmDeal::updateReminder($reminder['contact_id']);
        }
        $this->logAction('reminder_add', array('reminder_id' => $reminder['id']));
        $lm = new crmLogModel();
        $lm->log('reminder_add', $reminder['contact_id'], $reminder['id']);

        return $reminder['id'];
    }
}
