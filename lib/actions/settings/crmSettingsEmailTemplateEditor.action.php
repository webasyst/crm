<?php

class crmSettingsEmailTemplateEditorAction extends crmBackendViewAction
{
    public function execute()
    {
        $this->accessDeniedForNotAdmin();

        if ($this->getType() === 'form') {
            $cheat_sheet_key = 'message.form_source';
        } else {
            $cheat_sheet_key = 'message.email_source';
        }

        $data = array(
            'input_name' => null,
            'to_name' => null,
            'to_value' => null,
            'sourcefrom_name' => null,
            'sourcefrom_set' => null,
            'add_attachments_name' => null,
            'template' => $this->getDefaultMessageTemplate(),
            'message_to_variants' => $this->getMessageToVariants(),
            'cheat_sheet' => true,
            'cheat_sheet_key' => $cheat_sheet_key,
            'site_app_url' => wa()->getAppUrl('site'),
        );

        $data['to_value'] = key($data['message_to_variants']);
        $data = array_intersect_key(waRequest::request(), $data) + $data;
        $this->view->assign($data);
        $actions_path = wa('crm')->whichUI('crm') === '1.3' ? 'actions-legacy' : 'actions';
        $this->setTemplate('templates/' . $actions_path . '/settings/SettingsEmailEditor.inc.html');
    }

    protected function getType()
    {
        return $this->getParameter('type');
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
