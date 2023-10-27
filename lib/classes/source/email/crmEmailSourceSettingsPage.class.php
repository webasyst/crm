<?php

abstract class crmEmailSourceSettingsPage extends crmSourceSettingsPage
{
    /**
     * @var crmEmailSource
     */
    protected $source;

    /**
     * @override
     * @return string
     */
    protected function getConnectionSettingsBlock()
    {
        return '';
    }

    protected function getAssigns()
    {
        return array(
            'blocks' => $this->getBlocks(),
            'messages_block' => $this->getMessagesBlock($this->source),
            'connection_settings_block' => $this->getConnectionSettingsBlock(),
            'antispam_template_vars' => crmEmailSource::getAntiSpamTemplateVars(),
            'site_app_url' => wa()->getAppUrl('site'),
        );
    }

    protected function getBlocks()
    {
        foreach (array(
            new crmSourceSettingsWithContactViewBlock('with_contact', $this->source),
            new crmSourceSettingsResponsibleViewBlock('responsible', $this->source),
            new crmSourceSettingsCreateDealViewBlock('create_deal', $this->source)
        ) as $block) {
            $blocks[$block->getId()] = $block->render(array(
                'namespace' => 'source'
            ));
        }
        return $blocks;
    }

    protected function getTemplate()
    {
        $source_path = wa('crm')->whichUI('crm') === '1.3' ? 'source-legacy' : 'source';
        return 'templates/'.$source_path.'/settings/EmailSourceSettings.html';
    }

    /**
     * @override
     * @param $data
     * @return array
     */
    public function processSubmit($data)
    {
        $result = array(
            'status' => 'ok',
            'errors' => array(),
            'response' => array()
        );

        $data = $this->workupSubmitData($data);

        /**
         * @event source_settings_save
         * @param array $data
         * @return void
         */
        wa('crm')->event('source_settings_save', $data);

        $errors = $this->validateSubmit($data);
        if ($errors) {
            $result['status'] = 'failed';
            $result['errors'] = $errors;
            return $result;
        }

        $this->source->setConnectionParams($data['params']);

        $errors = $this->source->testConnection();
        if ($errors) {
            $result['status'] = 'failed';
            $result['errors'] = $errors;
            return $result;
        }

        $this->source->save($data);

        if ($this->source->getEmailSuffixSupporting() === crmEmailSource::EMAIL_SUFFIX_SUPPORTING_UNKNOWN) {
            $this->source->sendSuffixSupportingTestEmail();
        }

        $result['response'] = array(
            'source' => $this->source->getInfo()
        );

        return $result;
    }

    protected function workupSubmitData($data)
    {
        foreach ($data as $key => $value) {
            if (empty($value)) {
                unset($data[$key]);
            }
        }

        $data['params'] = (array)ifset($data['params']);
        foreach ($data['params'] as $key => $value) {
            if (empty($value)) {
                $data['params'][$key] = null; // will be deleted
                if ($key === 'password') {
                    unset($data['params'][$key]); // password must be saved, not changed
                }
            }
        }

        $data['params']['messages'] = (array)ifset($data['params']['messages']);
        if (empty($data['params']['messages'])) {
            $data['params']['messages'] = null;
        } else {
            $old_messages = $this->getMessages($this->source);
            foreach ($data['params']['messages'] as $idx => &$message) {
                $old_message_tmpl = '';
                if (isset($old_messages[$idx]['tmpl'])) {
                    $old_message_tmpl = $old_messages[$idx]['tmpl'];
                }
                // template of message is changed => user edit this template, so force it to smarty type
                if ($message['tmpl'] != $old_message_tmpl) {
                    $message['is_smarty_tmpl'] = true;
                }
            }
            unset($message);
        }

        if (empty($data['name']) && !empty($data['params']['email'])) {
            $data['name'] = $data['params']['email'];
        }

        if (empty($data['params']['after_antispam_confirm'])) {
            $data['params']['after_antispam_confirm'] = 'text';
        }
        if ($data['params']['after_antispam_confirm'] !== 'text' && $data['params']['after_antispam_confirm'] !== 'redirect') {
            $data['params']['after_antispam_confirm'] = 'text';
        }
        $url = ifset($data['params']['after_antispam_confirm_url']);
        if (!$url || $data['params']['after_antispam_confirm'] !== 'redirect') {
            $data['params']['after_antispam_confirm_url'] = null;
        }
        $text = (string)ifset($data['params']['after_antispam_confirm_text']);
        if (strlen($text) <= 0 || $data['params']['after_antispam_confirm'] !== 'text') {
            $data['params']['after_antispam_confirm_text'] = null;
        }

        if (empty($data['params']['create_deal'])) {
            $data['funnel_id'] = null;
            $data['stage_id'] = null;
        }
        if (empty($data['params']['segments'])) {
            $data['params']['segments'] = null;
        }
        if (empty($data['responsible_contact_id'])) {
            $data['responsible_contact_id'] = null;
        }

        return $data;
    }

    protected function validateSubmit($data)
    {
        $errors = array();

        foreach(array('server', 'email', 'port', 'login') as $field) {
            $data[$field] = (string)ifset($data['params'][$field]);
            if (empty($data[$field])) {
                $errors['params'][$field] = _ws('This field is required.');
            }
        }

        $email_validator = new waEmailValidator();
        if (!$email_validator->isValid($data['email'])) {
            $errors['params']['email'] = $email_validator->getErrors();
        }

        if ($data['params']['after_antispam_confirm'] === 'redirect') {
            $url = (string) ifset($data['params']['after_antispam_confirm_url']);
            if (strlen($url) <= 0) {
                $errors['params']['after_antispam_confirm_url'] = _ws('This field is required');
            } elseif (substr($url, 0, 7) !== 'http://' && substr($url, 0, 8) !== 'https://') {
                $errors['params']['after_antispam_confirm_url'] = _w('Invalid URL');
            }
        }

        return $errors;
    }

    /**
     * @param crmEmailSource $source
     * @return string
     */
    protected function getMessagesBlock(crmEmailSource $source)
    {
        $params = array(
            'namespace' => 'source[params][messages]',
            'messages' => $this->getMessages($source),
            'type' => 'source'
        );

        return crmHelper::renderViewAction(
            new crmSettingsMessagesBlockAction($params)
        );
    }

    private function getMessages(crmEmailSource $source)
    {
        $messages = $source->getMessages();
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
            '{ORIGINAL_SUBJECT}' => '{$original_subject}',
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
