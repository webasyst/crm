<?php

/**
 * List of reminders by user.
 */
class crmReminderShowAction extends crmReminderAction
{
    public function execute()
    {
        $this->reminder_id = waRequest::request('reminder_id', waRequest::param('reminder_id', null, waRequest::TYPE_INT), waRequest::TYPE_INT);

        parent::execute();

        $this->setTemplate('templates/actions/reminder/Reminder.html');
    }
}
