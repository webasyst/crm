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
        if (!empty($templates)) {
            ksort($templates);   
        }

        if ($template_id && isset($templates[$template_id])) {
            $template = $templates[$template_id];
        } elseif ($template_id == 'new') {
            $template = array_fill_keys(array_keys($tm->describe()), null);
            $template['origin_id'] = waRequest::get('origin_id', 1, waRequest::TYPE_INT);
        } elseif (empty($templates)) {
            $template = null;
            $templates = null;
        } else {
            $template = reset($templates);
        }

        $this->view->assign([
            'template'       => $template,
            'templates'      => $templates,
            'origin_variants' => $t->getTemplatesVariants(),
            'enum_params'    => crmTemplates::getParams(),
            'site_app_url'   => wa()->getAppUrl('site'),
            'basic_template' => $t->getOriginTemplate($template ? $template['origin_id'] : null),
            'basic_params'   => $t->getOriginTemplateParams($template ? $template['origin_id'] : null),
            'reset_template' => $t->isTemplateModified($template),
            'company_count'  => $cm->countByField('template_id', $template['id']),
        ]);
    }
}
