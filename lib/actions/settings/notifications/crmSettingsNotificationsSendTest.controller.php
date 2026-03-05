<?php

class crmSettingsNotificationsSendTestController extends crmSettingsNotificationsEditSaveController
{
    protected $warnings = [];

    public function execute()
    {
        $data = $this->getData();

        $errors = $this->validate($data);
        if ($errors) {
            $this->errors = $errors;
            return;
        }

        $is_deal_event = (stripos($data['event'], 'deal.') === 0);
        $render_class = 'crmTemplates' . ($is_deal_event ? 'Deal' : '') . 'Render';
        $this->errors = [];

        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            // suppress possible warnings
            if (0 === error_reporting()) {
                return false;
            }
            if (strpos($errstr, 'filemtime()') !== false || strpos($errstr, 'unlink(') !== false) {
                return false;
            }
            $this->warnings[] = $errstr;
        });

        try {
            if ($data['transport'] === crmNotificationModel::TRANSPORT_HTTP) {
                $data['headers'] = empty($data['headers']) ? '' : $render_class::render(array('template' => $data['headers'], 'ignore_template_errors' => true));
                $data['get'] = empty($data['get']) ? '' : $render_class::render(array('template' => $data['get'], 'ignore_template_errors' => true));
                $data['post'] = empty($data['post']) ? '' : $render_class::render(array('template' => $data['post'], 'ignore_template_errors' => true));
            }

            if (in_array($data['transport'], array(crmNotificationModel::TRANSPORT_EMAIL, crmNotificationModel::TRANSPORT_SMS))) {
                $data['body'] = empty($data['body']) ? '' : $render_class::render(array('template' => $data['body']));
            }

            if ($data['transport'] === crmNotificationModel::TRANSPORT_EMAIL) {
                $data['subject'] = empty($data['subject']) ? '' : $render_class::render(array('template' => $data['subject']));
            }

            if ($data['transport'] === crmNotificationModel::TRANSPORT_REMINDER) {
                $data['reminder_content'] = empty($data['reminder_content']) ? '' : $render_class::render(array('template' => $data['reminder_content']));
            }

            $cn = crmNotification::factory($data);
            if (!($cn instanceof crmNotification)) {
                $this->errors = [ _w('Notification not found for this event type.') ];
                return;
            }

            $res = $cn->sendTestNotification($data['contact']);
        } catch (Exception $e) {
            $this->errors = [ $e->getMessage() ];
        }

        if (!empty($res['errors'])) {
            $this->errors = array_merge($this->errors, $res['errors'], $this->warnings);
        } elseif (!empty($res['data'])) {
            $this->response = $res['data'];
            if (!empty($this->warnings)) {
                $this->response['warnings'] = $this->warnings;
            }
        }
    }
}
