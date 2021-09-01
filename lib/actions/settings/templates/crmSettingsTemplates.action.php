<?php

class crmSettingsTemplatesAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $t = new crmTemplates();
        $tm = new crmTemplatesModel();
        $tpm = new crmTemplatesParamsModel();
        $cm = new crmCompanyModel();

        $template_id = waRequest::param('id');

        $templates = $tpm->withParams($tm->getAll('id'));

        if ($template_id && isset($templates[$template_id])) {
            $template = $templates[$template_id];
        } elseif ($template_id == 'new') {
            $template = array_fill_keys(array_keys($tm->describe()), null);
        } elseif (!$templates) {
            $template = null;
            $templates = null;
        } else {
            $template = reset($templates);
        }

        $this->view->assign(array(
            'template'       => $template,
            'templates'      => $templates,
            'enum_params'    => crmTemplates::getParams(),
            'site_app_url'   => wa()->getAppUrl('site'),
            'basic_template' => $t->getBasicTemplate($template ? $template['id'] : null),
            'reset_template' => $t->isTemplateModified($template),
            'company_count'  => $cm->countByField('template_id', $template['id']),
        ));
    }
}
