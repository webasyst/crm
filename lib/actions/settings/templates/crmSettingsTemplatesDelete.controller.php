<?php

class crmSettingsTemplatesDeleteController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $template_id = waRequest::post('id', null, waRequest::TYPE_INT);

        $tm = new crmTemplatesModel();
        $tpm = new crmTemplatesParamsModel();
        $cpm = new crmCompanyParamsModel();
        $cm = new crmCompanyModel();

        if ($tm->countAll() < 2) {
            throw new waException(_w('Cannot delete the last template.'));
        }

        if ($cm->countByField('template_id', $template_id) == 0) {
            //drop_old_params
            $tpm->deleteByField('template_id', $template_id);
            $tm->deleteById($template_id);
            $cpm->deleteByField('template_id', $template_id);

            crmCompanyImageHandler::deleteTemplateImages($template_id);
        } else {
            throw new waException('The template is associated with companies');
        }
    }
}
