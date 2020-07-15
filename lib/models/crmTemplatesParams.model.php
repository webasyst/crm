<?php

class crmTemplatesParamsModel extends crmModel
{
    protected $table = 'crm_template_params';

    public function withParams($templates)
    {
        if (!$templates) {
            return;
        }

        $params = $this->select('*')->where('template_id IN (?)', array(array_keys($templates)))->order('sort')->fetchAll();

        foreach ($params as $key => $field) {
            $templates[$field['template_id']]['params'][$key] = $field;
        }

        return $templates;
    }

    public function getParamsByTemplates($template_id)
    {
        if (!$template_id) {
            return;
        }
        $getParams = $this->select('*')->where('template_id IN (?)', $template_id)->order('sort')->query()->fetchAll('code');

        return $getParams;
    }

}
