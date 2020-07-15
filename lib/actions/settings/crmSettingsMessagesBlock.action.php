<?php

class crmSettingsMessagesBlockAction extends crmBackendViewAction
{
    public function execute()
    {
        $this->accessDeniedForNotAdmin();

        /**
         * @event backend_settings_messages_block
         * @return array[string][string]string $return[%plugin_id%]['top'] html output
         * @return array[string][string]string $return[%plugin_id%]['bottom'] html output
         */
        $backend_settings_messages_block = wa('crm')->event('backend_settings_messages_block');

        $this->view->assign(array(
            'namespace' => $this->getNamespace(),
            'messages' => $this->getMessages(),
            'message_template_vars' => $this->getMessageTemplateVars(),
            'default_message_template' => $this->getDefaultMessageTemplate(),
            'message_to_variants' => $this->getMessageToVariants(),
            'type' => $this->getType(),
            'backend_settings_messages_block' => $backend_settings_messages_block,
        ));
    }

    protected function getType()
    {
        return $this->getParameter('type');
    }

    protected function getNamespace()
    {
        return $this->getParameter('namespace');
    }

    protected function getMessages()
    {
        return (array)$this->getParameter('messages');
    }

    protected function getMessageTemplateVars()
    {
        if ($this->getType() === 'form') {
            return crmForm::getMessageTemplateVars();
        } elseif ($this->getType() === 'source') {
            return crmEmailSource::getMessageTemplateVars();
        } else {
            return array();
        }
    }

    protected function getDefaultMessageTemplate()
    {
        if ($this->getType() === 'form') {
            return crmForm::getDefaultMessageMailTemplate();
        } elseif ($this->getType() === 'source') {
            return crmEmailSource::getDefaultMessageMailTemplate();
        } else {
            return array();
        }
    }

    protected function getMessageToVariants()
    {
        if ($this->getType() === 'form') {
            return crmForm::getMessageToVariants();
        } elseif ($this->getType() === 'source') {
            return crmEmailSource::getMessageToVariants();
        } else {
            return array();
        }
    }
}
