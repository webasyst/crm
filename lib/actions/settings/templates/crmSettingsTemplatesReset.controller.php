<?php

class crmSettingsTemplatesResetController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $template_id = waRequest::post('template_id', null, waRequest::TYPE_INT);
        $t = new crmTemplates();

        $template = $t->getBasicTemplate($template_id);

        $this->response = array(
            'template' => $template
        );

    }
}
