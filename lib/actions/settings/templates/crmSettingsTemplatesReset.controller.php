<?php

class crmSettingsTemplatesResetController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $template_id = waRequest::post('template_id', null, waRequest::TYPE_INT);
        $template_record = (new crmTemplatesModel)->getById($template_id);
        $template = (new crmTemplates)->getOriginTemplate(ifset($template_record['origin_id']));

        $this->response = array(
            'template' => $template
        );

    }
}
