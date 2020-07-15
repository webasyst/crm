<?php

class crmSettingsNotificationsSendTestController extends crmSettingsNotificationsEditSaveController
{
    public function execute()
    {
        $data = $this->getData();

        $errors = $this->validate($data);
        if ($errors) {
            $this->errors = $errors;
            return;
        }

        $data['body'] = crmTemplatesRender::render(array('template' => $data['body']));
        $data['subject'] = crmTemplatesRender::render(array('template' => $data['subject']));

        $cn = crmNotification::factory($data);
        if ($cn instanceof crmNotification) {
            $cn->sendTestNotification($data['contact']);
        }
    }
}
