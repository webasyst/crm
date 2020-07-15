<?php
/**
 * Mark reminder as uncompleted.
 */
class crmReminderMarkAsUndoneController extends crmJsonController
{
    public function execute()
    {
        $id = waRequest::request('id', null, waRequest::TYPE_INT);

        $rm = new crmReminderModel();
        $reminder = $rm->getById($id);

        if (!$id || !$reminder || !$reminder['complete_datetime']) {
            throw new waException('Reminder not found');
        }
        if (!$this->getCrmRights()->reminderEditable($reminder)) {
            throw new waRightsException();
        }
        $rm->updateById($id, array('complete_datetime' => null));

        try {
            crmDeal::updateReminder($reminder['contact_id']);
        } catch (waException $e) {
        }

        $this->logAction('reminder_undone', array('reminder_id' => $id));
    }
}
