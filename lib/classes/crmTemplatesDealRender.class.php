<?php

class crmTemplatesDealRender extends waController
{
    /**
     * @array data deal + data company
     */
    public $_content;

    /**
     * @array data deal
     */
    public $deal;

    /**
     * @int crm_deal.id
     */
    public $deal_id;

    /**
     * @array data company
     */
    public $company;

    /**
     * @int crm_company.template_id
     */
    public $deal_template_id;

    /**
     * @string html with smarty variables
     */
    public $template;

    /**
     * @int crm_company.company_id
     */
    public $company_id;

    /**
     * @array info about customer
     */
    public $customer;

    /**
     * @array info about responsible
     */
    public $responsible;

    private $options;

    /**
     * If options have an deal_id, then the data of a particular deal will be received, otherwise the base deal data will be received
     * crmTemplatesDealRender constructor.
     * @param array $options
     * @throws Exception
     */
    public function __construct($options = array())
    {
        $options += array(
            'deal_template_id' => null,
            'company_id'       => null,
            'template'         => null,
            'deal_id'          => null,
            'ignore_template_errors' => false,
        );

        $this->deal_template_id = $options['deal_template_id'];
        $this->template = ifempty($options['template']);
        $this->company_id = $options['company_id'];

        $path = wa('crm')->getConfig()->getAppPath('lib/config/data/template_deal_info.php');
        if (!file_exists($path)) {
            throw new Exception(_w('Basic template info not found.'));
        }
        $this->_content = include($path);

        $this->deal = $this->_content['deal'];

        if (isset($options['company_id'])) {
            $cm = new crmCompanyModel();
            $this->company = $cm->getById($options['company_id']);
        } else {
            $this->company = $this->_content['company'];
        }
        $this->customer = $this->responsible = wa()->getUser();
        $this->options = $options;
    }

    public static function render($options = array())
    {
        $render = new crmTemplatesDealRender($options);
        return $render->getRenderedTemplate();
    }

    public function __toString()
    {
        return $this->getRenderedTemplate();
    }

    /**
     * @return string html with the supplied deal data
     */
    public function getRenderedTemplate()
    {
        $view = wa()->getView();

        $view->assign(array(
            'deal'        => $this->deal,
            'customer'    => $this->customer,
            'responsible' => $this->responsible,
            'company'     => $this->getStaticInfoCompany(),
        ));
        try {
            return $view->fetch('string:'.$this->getTemplate());
        } catch (Exception $e) {
            if (!$this->options['ignore_template_errors']) {
                throw $e;
            }
            return $this->getTemplate();
        }
    }

    /**
     * If company_id is known, then a particular company will be received, otherwise general parameters will be received
     * @return array company data + template params
     */
    public function getStaticInfoCompany()
    {
        $tpm = new crmTemplatesParamsModel();
        $params = $tpm->getParamsByTemplates($this->deal_template_id);
        $company = $this->company;
        $company['deal_options'] = null;
        $arrCompany_template_params = null;

        if (isset($this->company['id'])) {
            $arrCompany_template_params = $this->getTemplateParamsForCompany();
        }

        if (isset($this->company['logo'])) {
            $company['logo_url'] = wa()->getDataUrl('logos/'.$this->company_id.'.original.'.$this->company['logo'], true, 'crm').'?'.rand(1, 1000);
        }

        if ($arrCompany_template_params) {
            foreach ($arrCompany_template_params as $key => $param) {

                if (isset($params, $params[$key], $this->company_id, $this->deal_template_id) && $params[$key]['type'] == 'IMAGE') {
                    $company['deal_options'][$key] = wa()->getDataUrl('company_images/'.$this->company_id.'.original.'.$key.'.'.$this->deal_template_id.'.'.$param, true,
                        'crm');
                } else {
                    $company['deal_options'][$key] = $param;

                }
            }
        }
        return $company;
    }

    /**
     * @return array crm_company.deal_options
     */
    public function getTemplateParamsForCompany()
    {
        $cpm = new crmCompanyParamsModel();
        $tpm = new crmTemplatesParamsModel();

        $company_params = $cpm->getParams($this->company_id, $this->deal_template_id);
        $params_description = $tpm->getByField('template_id', $this->company['template_id'], 'code');

        $params = array();

        foreach ($params_description as $code => $p) {
            if (ifset($company_params[$code])) {
                $params[$code] = $company_params[$code];
            } else {
                $params[$code] = null;
            }
        }

        return $params;
    }

    /**
     * @string html basic_template
     */
    public function getTemplate()
    {
        return $this->template;
    }
}
