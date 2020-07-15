<?php

class crmCompanyParamsModel extends crmModel
{
    protected $table = 'crm_company_params';

    public function getParams($company_id, $template_id)
    {
        $params = array();

        if (!$company_id || !$template_id) {
            return $params;
        }

        $params = $this->select('`name`, `value`')->where('`company_id` IN (?) AND `template_id` IN (?)', array($company_id, $template_id))->fetchAll('name',true);

        return $params;
    }
}