<?php
/**
 * Mark reminder completed.
 */
class crmReminderMarkAsDoneController extends crmJsonController
{
    public function execute()
    {
        $id = waRequest::request('id', null, waRequest::TYPE_INT);
        $user_id = waRequest::request('user_id', wa()->getUser()->getId(),waRequest::TYPE_STRING_TRIM);
        $contact_id = waRequest::get('contact', null, waRequest::TYPE_INT);
        $deal_id = abs(waRequest::get('deal', 0, waRequest::TYPE_INT));

        $rm = new crmReminderModel();
        $reminder = $rm->getById($id);

        if (!$id || !$reminder || $reminder['complete_datetime']) {
            throw new waException('Reminder not found');
        }
        if (!$this->getCrmRights()->reminderEditable($reminder)) {
            throw new waRightsException();
        }

        $result = $rm->updateById($id, array('complete_datetime' => date('Y-m-d H:i:s')));
        if ($result) {
            $user_id = ($user_id === 'all' ? null : $user_id);
            if (!empty($deal_id)) {
                $condition = 'contact_id = '.((int) $deal_id * -1);
            } elseif (!empty($contact_id)) {
                $deals = (new crmDealModel())->select('id')->where('contact_id = ?', $contact_id)->fetchAll('id');
                $deal_ids = array_map(function ($_deal_id) {return $_deal_id * -1;}, array_keys($deals));
                $condition = 'contact_id IN ('.implode(',', [$contact_id] + $deal_ids).')';
            } elseif (!empty($user_id)) {
                $condition = 'user_contact_id = '.$user_id;
            } else {
                $condition = '1=1';
            }
            $condition .= ' AND complete_datetime IS NOT NULL';
            $completed_reminders_count = $rm->select('COUNT(*) cnt')
                ->where($condition)
                ->fetchField('cnt');
            $this->response = [
                'completed_title' => _w('%d completed reminder', '%d completed reminders', $completed_reminders_count)
            ];
        }

        if ($reminder['user_contact_id'] != $user_id || $reminder['creator_contact_id'] != $user_id) {
            $c = new waContact($reminder['user_contact_id']);
            if (!$c->getSettings('crm', 'reminder_disable_done')) {
                $to = array();
                if ($reminder['user_contact_id'] != $user_id) {
                    $to[$reminder['user_contact_id']] = 1;
                }
                if ($reminder['creator_contact_id'] != $user_id) {
                    $to[$reminder['creator_contact_id']] = 1;
                }
                $locale = $c->getLocale();
                $old_locale = wa()->getLocale();
                if ($locale != $old_locale) {
                    wa()->setLocale($locale);
                }
                crmReminder::sendNotification($reminder, array_keys($to), 'reminder_done', _w('Done').': ');
                if ($locale != $old_locale) {
                    wa()->setLocale($old_locale);
                }
            }
        }

        try {
            crmDeal::updateReminder($reminder['contact_id']);
        } catch (waException $e) {
        }

        $this->logAction('reminder_done', array('reminder_id' => $id));
        $lm = new crmLogModel();
        $lm->log('reminder_done', $reminder['contact_id'], $id);
    }
}
