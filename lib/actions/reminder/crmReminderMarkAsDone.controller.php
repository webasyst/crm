<?php
/**
 * Mark reminder completed.
 */
class crmReminderMarkAsDoneController extends crmJsonController
{
    public function execute()
    {
        $id = waRequest::request('id', null, waRequest::TYPE_INT);

        $rm = new crmReminderModel();
        $reminder = $rm->getById($id);

        if (!$id || !$reminder || $reminder['complete_datetime']) {
            throw new waException('Reminder not found');
        }
        if (!$this->getCrmRights()->reminderEditable($reminder)) {
            throw new waRightsException();
        }

        $rm->updateById($id, array('complete_datetime' => date('Y-m-d H:i:s')));

        if ($reminder['user_contact_id'] != wa()->getUser()->getId() || $reminder['creator_contact_id'] != wa()->getUser()->getId()) {
            $c = new waContact($reminder['user_contact_id']);
            if (!$c->getSettings('crm', 'reminder_disable_done')) {
                $to = array();
                if ($reminder['user_contact_id'] != wa()->getUser()->getId()) {
                    $to[$reminder['user_contact_id']] = 1;
                }
                if ($reminder['creator_contact_id'] != wa()->getUser()->getId()) {
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
