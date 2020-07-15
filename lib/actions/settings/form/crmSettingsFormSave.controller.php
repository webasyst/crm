<?php

class crmSettingsFormSaveController extends crmJsonController
{
    public function execute()
    {
        $this->accessDeniedForNotAdmin();

        $data = $this->getData();

        $errors = $this->validate($data);
        if ($errors) {
            $this->errors = $errors;
            return;
        }

        $id = $this->getParameter('id');

        $form_constructor = new crmFormConstructor($id);
        $success = $form_constructor->saveForm($data);

        if ($success) {
            $this->logAction('create_signup_form');
        }

        $this->response = array(
            'form' => $form_constructor->getFormInfo()
        );
    }

    protected function getData()
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
            return _w('Invalid url');
        }
        return null;
    }
}
