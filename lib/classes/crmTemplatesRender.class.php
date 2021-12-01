<?php

class crmTemplatesRender extends waController
{
    /**
     * @array data invoice + data company
     */
    public $_content;

    /**
     * @array data invoice
     */
    public $invoice;

    /**
     * @int crm_invoice.id
     */
    public $invoice_id;

    /**
     * @array data company
     */
    public $company;

    /**
     * @int crm_company.template_id
     */
    public $invoice_template_id;

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
     * If options have an invoice_id, then the data of a particular invoice will be received, otherwise the base invoice data will be received
     * crmTemplatesRender constructor.
     * @param array $options
     * @throws Exception
     */
    public function __construct($options = array())
    {
        $options += array(
            'invoice_template_id' => null,
            'company_id'          => null,
            'template'            => null,
            'invoice_id'          => null
        );

        $this->invoice_template_id = $options['invoice_template_id'];
        $this->template = ifempty($options['template']);
        $this->company_id = $options['company_id'];

        $path = wa('crm')->getConfig()->getAppPath('lib/config/data/template_info.php');
        if (!file_exists($path)) {
            throw new Exception(_w('Basic template info not found.'));
        }
        $this->_content = include($path);

        if ($options['invoice_id']) {
            $this->invoice_id = $options['invoice_id'];
            $this->setInvoiceOptions();
        } else {
            $this->invoice = $this->_content['invoice'];

            if (isset($options['company_id'])) {
                $cm = new crmCompanyModel();
                $this->company = $cm->getById($options['company_id']);
            } else {
                $this->company = $this->_content['company'];
            }
            $this->customer = wa()->getUser();
        }
    }

    public static function render($options = array())
    {
        $render = new crmTemplatesRender($options);
        return $render->getRenderedTemplate();
    }

    public function __toString()
    {
        return $this->getRenderedTemplate();
    }

    /**
     * @return string html with the supplied invoice data
     */
    public function getRenderedTemplate()
    {
        $view = wa()->getView();

        $view->assign(array(
            'invoice'  => $this->invoice,
            'customer' => $this->customer,
            'company'  => $this->getStaticInfoCompany(),
            'link'     => $this->_content['link'],
        ));

        return $view->fetch('string:'.$this->getTemplate());
    }

    /**
     * If company_id is known, then a particular company will be received, otherwise general parameters will be received
     * @return array company data + template params
     */
    public function getStaticInfoCompany()
    {
        $tpm = new crmTemplatesParamsModel();
        $params = $tpm->getParamsByTemplates($this->invoice_template_id);
        $company = $this->company;
        $company['invoice_options'] = null;
        $arrCompany_template_params = null;

        if (isset($this->company['id'])){
            $arrCompany_template_params = $this->getTemplateParamsForCompany();
        }

        if (isset($this->company['logo'])) {
            $company['logo_url'] = wa()->getDataUrl('logos/'.$this->company_id.'.original.'.$this->company['logo'], true, 'crm').'?'.rand(1, 1000);
        }

        if ($arrCompany_template_params) {
            foreach ($arrCompany_template_params as $key => $param) {

                if (isset($params, $params[$key], $this->company_id, $this->invoice_template_id) && $params[$key]['type'] == 'IMAGE') {
                    $company['invoice_options'][$key] = wa()->getDataUrl('company_images/'.$this->company_id.'.original.'.$key.'.'.$this->invoice_template_id.'.'.$param, true,
                        'crm');
                } else {
                    $company['invoice_options'][$key] = $param;

                }
            }
        }
        return $company;
    }

    /**
     * @return array crm_company.invoice_options
     */
    public function getTemplateParamsForCompany()
    {
        $cpm = new crmCompanyParamsModel();
        $tpm = new crmTemplatesParamsModel();

        $company_params = $cpm->getParams($this->company_id, $this->invoice_template_id);
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
        if (!$this->template) {
            $tm = new crmTemplatesModel();
            $template = $tm->getById($this->invoice_template_id);
            $this->template = ifset($template, 'content', null);

            if (!$this->template) {
                $file = wa('crm')->getConfig()->getAppPath('lib/config/data/templates/invoice.template_a.html');
                if (!file_exists($file)) {
                    throw new Exception(_w('Basic template info not found.'));
                }
                $this->template = file_get_contents($file);
            }
        }

        return $this->template;
    }

    /**
     * Fill in the account and the company with specific data
     */
    public function setInvoiceOptions()
    {
        // Get invoice data
        $im = new crmInvoiceModel();
        $this->invoice = $im->getInvoiceWithCompany($this->invoice_id);
        $this->company_id = $this->invoice['company_id'];
        $company = ifset($this->invoice, 'company', null);
        unset($this->invoice['company']);

        $this->invoice_template_id = ifset($company, 'template_id', null);

        try {
            $this->customer = new waContact($this->invoice['contact_id']);
            $this->customer->getName();
        } catch (waException $e) {
            $this->customer = new waContact();
            $this->customer['name'] = sprintf_wp('deleted id=%d', $this->invoice['contact_id']);
        }

        return $this->company = $company;
    }
}
