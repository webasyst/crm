<?php

class crmSettingsEmailTemplateEditorAction extends crmBackendViewAction
{
    public function execute()
    {
        $this->accessDeniedForNotAdmin();

        $data = array(
            'input_name' => null,
            'to_name' => null,
            'to_value' => null,
            'sourcefrom_name' => null,
            'sourcefrom_set' => null,
            'add_attachments_name' => null,
            'variables' => $this->getMessageTemplateVars(),
            'template' => $this->getDefaultMessageTemplate(),
            'message_to_variants' => $this->getMessageToVariants(),
        );
        $data['to_value'] = key($data['message_to_variants']);
        $data = array_intersect_key(waRequest::request(), $data) + $data;
        $this->view->assign($data);
        $this->setTemplate('templates/actions/settings/SettingsEmailEditor.inc.html');
    }

    protected function getType()
    {
        return $this->getParameter('type');
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
