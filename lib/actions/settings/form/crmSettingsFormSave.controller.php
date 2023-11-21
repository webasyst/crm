<?php

class crmSettingsFormSaveController extends crmJsonController
{
    public function execute()
    {
        $this->accessDeniedForNotAdmin();

        $id = $this->getParameter('id');
        $form = new crmForm($id);

        $data = $this->getData($form);

        $errors = $this->validate($data);
        if ($errors) {
            $this->errors = $errors;
            return;
        }

        $form_constructor = new crmFormConstructor($id);
        $success = $form_constructor->saveForm($data);

        if ($success) {
            $this->logAction('create_signup_form');
        }

        $this->response = array(
            'form' => $form_constructor->getFormInfo()
        );
    }

    protected function getData(crmForm $form_instance)
    {
        $form = (array) $this->getRequest()->post('form');

        $form['params'] = (array) ifset($form['params']);
        $fields = json_decode(trim(ifset($form['params']['fields'])), true);
        $form['params']['fields'] = $fields;

        if (empty($form['params']['after_submit'])) {
            $form['params']['after_submit'] = 'html';
        }

        if ($form['params']['after_submit'] !== 'html' && $form['params']['after_submit'] !== 'redirect') {
            $form['params']['after_submit'] = 'html';
        }

        $url = ifset($form['params']['redirect_after_submit']);
        if (!$url || $form['params']['after_submit'] !== 'redirect') {
            $form['params']['redirect_after_submit'] = null;
        }

        $text = (string) ifset($form['params']['html_after_submit']);
        if (strlen($text) <= 0 || $form['params']['after_submit'] !== 'html') {
            $form['params']['html_after_submit'] = null;
        }

        $form['params']['messages'] = (array)ifset($form['params']['messages']);
        if (empty($form['params']['messages'])) {
            $form['params']['messages'] = null;
        } else {
            $old_messages = $this->getMessages($form_instance);
            foreach ($form['params']['messages'] as $idx => &$message) {
                $old_message_tmpl = '';
                if (isset($old_messages[$idx]['tmpl'])) {
                    $old_message_tmpl = $old_messages[$idx]['tmpl'];
                }
                // template of message is changed => user edit this template, so force it to smarty type
                if ($message['tmpl'] != $old_message_tmpl) {
                    $message['is_smarty_tmpl'] = true;
                } elseif (empty($message['is_smarty_tmpl']) && ifset($old_messages, $idx, 'is_smarty_tmpl', false)) {
                    $message['is_smarty_tmpl'] = ifset($old_messages, $idx, 'is_smarty_tmpl', false);
                }
            }
            unset($message);
        }

        if (empty($form['params']['after_antispam_confirm'])) {
            $form['params']['after_antispam_confirm'] = 'text';
        }

        if ($form['params']['after_antispam_confirm'] !== 'text' && $form['params']['after_antispam_confirm'] !== 'redirect') {
            $form['params']['after_antispam_confirm'] = 'text';
        }

        $url = ifset($form['params']['after_antispam_confirm_url']);
        if (!$url || $form['params']['after_antispam_confirm'] !== 'redirect') {
            $form['params']['after_antispam_confirm_url'] = null;
        }

        $text = (string) ifset($form['params']['after_antispam_confirm_text']);
        if (strlen($text) <= 0 || $form['params']['after_antispam_confirm'] !== 'text') {
            $form['params']['after_antispam_confirm_text'] = null;
        }

        if (empty($form['params']['create_deal'])) {
            $form['source']['funnel_id'] = null;
            $form['source']['stage_id'] = null;
        }

        if (empty($form['source']['responsible_contact_id'])) {
            $form['source']['responsible_contact_id'] = null;
        }

        return $form;
    }

    protected function validate($data)
    {
        $errors = array();
        $name = (string) ifset($data['name']);
        if (strlen($name) <= 0) {
            $errors['name'] = _ws('This field is required.');
        }

        if ($data['params']['after_antispam_confirm'] === 'redirect') {
            $error = $this->validateUrl(ifset($data['params']['after_antispam_confirm_url']));
            if ($error) {
                $errors['params']['after_antispam_confirm_url'] = $error;
            }
        }

        if ($data['params']['after_submit'] === 'redirect') {
            $error = $this->validateUrl(ifset($data['params']['redirect_after_submit']));
            if ($error) {
                $errors['params']['redirect_after_submit'] = $error;
            }
        }

        return $errors;
    }

    protected function validateUrl($url)
    {
        $url = (string) $url;
        if (strlen($url) <= 0) {
            return _ws('This field is required');
        } elseif (substr($url, 0, 7) !== 'http://' && substr($url, 0, 8) !== 'https://') {
            return _w('Invalid URL');
        }
        return null;
    }

    private function getMessages(crmForm $form)
    {
        $messages = $form->getMessages();
        foreach ($messages as &$message) {
            if (empty($message['is_smarty_tmpl'])) {
                $message['is_smarty_tmpl'] = true;
                $tmpl = $this->convertToSmarty($message['tmpl']);
                $message['tmpl'] = $tmpl;
            }
        }
        unset($message);
        return $messages;
    }

    private function convertToSmarty($tmpl)
    {
        $convert = [
            '{ORIGINAL_TEXT}' => '{$original_text}',
            '{COMPANY_NAME}' => '{$company_name|escape}',
            '{CUSTOMER_ID}' => '{$customer.id}',
            '{CUSTOMER_NAME}' => '{$customer.getName()|escape}',
        ];

        foreach (waContactFields::getAll() as $field_id => $field) {
            $key = '{CUSTOMER_' . strtoupper($field_id) . '}';
            $value = "{\$customer.get('{$field_id}', 'default')|escape}";
            $convert[$key] = $value;
        }

        return str_replace(array_keys($convert), array_values($convert), $tmpl);
    }
}
