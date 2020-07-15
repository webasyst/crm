<?php
/**
 * Delete a reminder.
 */
class crmReminderDeleteController extends crmJsonController
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
        $rm->deleteById($id);

        crmDeal::updateReminder($reminder['contact_id']);
    }
}
