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
     * @int crm_invoice.id
     */
    public $origin_id;

    /**
     * @int crm_company.company_id
     */
    public $company_id;

    /**
     * @array info about customer
     */
    public $customer;

    private $options;

    /**
     * If options have an invoice_id, then the data of a particular invoice will be received, otherwise the base invoice data will be received
     * crmTemplatesRender constructor.
     * @param array $options
     * @throws Exception
     */
    public function __construct($options = array())
    {
        $options += [
            'invoice_template_id' => null,
            'company_id'          => null,
            'template'            => null,
            'invoice_id'          => null,
            'invoice'             => null,
            'origin_id'           => null,
        ];

        $this->invoice_template_id = $options['invoice_template_id'];
        $this->invoice_id = $options['invoice_id'];
        $this->invoice = $options['invoice'];
        $this->template = $options['template'];
        $this->origin_id = $options['origin_id'];
        $this->company_id = $options['company_id'];
        $this->options = $options;

        $path = wa('crm')->getConfig()->getAppPath('lib/config/data/template_info.php');
        if (!file_exists($path)) {
            throw new Exception(_w('Basic template info not found.'));
        }
        $this->_content = include($path);

        if (!empty($this->invoice_id) || !empty($this->invoice)) {
            $this->setInvoiceOptions();
        } else {
            $this->invoice = $this->_content['invoice'];

            if (isset($options['company'])) {
                $this->company = $options['company'];
            } elseif (isset($options['company_id'])) {
                $cm = new crmCompanyModel();
                $this->company = $cm->getById($options['company_id']);
            } else {
                $this->company = $this->_content['company'];
            }
            $this->company['invoice_options'] = self::getCompanyTemplateParams($this->company, $this->invoice_template_id, $this->origin_id);
            $this->invoice['company'] = $this->company;
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

        $view->assign([
            'invoice'  => $this->invoice,
            'pay_button'   => $this->getRenderedPayButton(),
            'customer' => $this->customer,
            'company'  => $this->company,
            'link'     => $this->_content['link'],
        ]);

        return $view->fetch('string:'.$this->getTemplate());
    }

    protected function getRenderedPayButton()
    {
        if (!empty($this->options['style_version']) && !empty($this->options['invoice']['state_id']) && in_array($this->options['invoice']['state_id'], ['PROCESSING', 'PAID'])) {
            if ($this->options['invoice']['state_id'] == 'PROCESSING') {
                return '<p style="text-align:center; font-size: 1.5rem;"><span style="color:#7256ee"><b>'._w('Payment is in process').'</b></span></p>';
            } elseif ($this->options['invoice']['state_id'] == 'PAID') {
                return '<p style="text-align:center; font-size: 1.5rem;"><span style="color:#22d13d"><b>'._w('Invoice is paid').'</b></span></p>';
            }
        }
        if (wa()->getEnv() == 'frontend') {
            if ($this->options['invoice']['state_id'] == 'PENDING' && waRequest::get('result') == 'success') {
                return '';
            }
            
            $file = wa('crm')->getConfig()->getAppPath('templates/actions/frontend/invoice/payment.section.inc.html');
            if (!file_exists($file)) {
                throw new Exception(_w('Basic template info not found.'));
            }
            $button_template = file_get_contents($file);
            $view = wa()->getView();
            $view->assign([
                'invoice'  => $this->invoice,
                'customer' => $this->customer,
                'company'  => $this->company,
            ] + $this->options);
            return $view->fetch('string:'.$button_template);
        }
        return '<a href="javascript:void(0);" class="c-button js-show-payments"'.(
                    !empty($this->company['invoice_options']['button_color']) ? 
                        ' style="background-color: '.$this->company['invoice_options']['button_color'].';"' : ''
                ).'>'._w('Pay').'</a>';
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
            $this->origin_id = ifset($template, 'origin_id', null);

            if (!$this->template) {
                $file = wa('crm')->getConfig()->getAppPath('lib/config/data/templates/invoices/invoice.template_a.html');
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
        if (empty($this->invoice)) {
            // Get invoice data
            $this->invoice = (new crmInvoiceModel)->getInvoiceWithCompany($this->invoice_id);
        } else {
            $this->invoice_id = $this->invoice['id'];
        }
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

    public static function getCompanyTemplateParams($company, $template_id = null, $origin_id = null)
    {
        $company_params = [];
        if (!empty($template_id) && !empty($company)) {
            $company['template_id'] = $template_id;
        }
        if (!empty($company['template_id']) && !empty($company['id'])) {
            $template_params = (new crmTemplatesParamsModel)->getParamsByTemplates($company['template_id']);
            $company_params = (new crmCompanyParamsModel)->getParams($company['id'], $company['template_id']);
            foreach ($company_params as $key => $value) {
                if (empty($value)) {
                    continue;
                }
                if (ifset($template_params[$key]['type']) === crmTemplatesModel::PARAM_TYPE_IMAGE) {
                    $company_params[$key] = wa()->getDataUrl('company_images/'.$company['id'].'.original.'.$key.'.'.$company['template_id'].'.'.$value, true, 'crm');
                } elseif ($key === 'bg_color') {
                    $company_params[$key] = self::getBgColor($value);
                }
            }
        }
        if (empty($origin_id) && !empty($company['template_id'])) {
            $template_record = (new crmTemplatesModel())->getById($company['template_id']);
            if (!empty($template_record['origin_id'])) {
                $origin_id = $template_record['origin_id'];
            }
        }
        if (!empty($origin_id)) {
            $origin_params = (new crmTemplates())->getOriginTemplateParams($origin_id);
            $origin_params = array_filter($origin_params, function ($value) {
                return !empty($value['default']);
            });
            foreach ($origin_params as $value) {
                $_key = $value['code'];
                if (empty($company_params[$_key])) {
                    if ($value['type'] === crmTemplatesModel::PARAM_TYPE_IMAGE) {
                        $company_params[$_key] = wa()->getRootUrl() . 'wa-apps/crm' . $value['default'];
                    } elseif ($_key === 'bg_color') {
                        $company_params[$_key] = self::getBgColor($value['default']);
                    } else {
                        $company_params[$_key] = $value['default'];
                    }
                }
            }
        }
        
        return $company_params;
    }

    protected static function getBgColor($color)
    {
        if (empty($color)) {
            return null;
        }
        
        $color = is_scalar($color) ? trim(strval($color)) : '';
        if (strlen($color) <= 0 || $color[0] != '#' || strlen($color) != 7) {
            return null;
        }

        $c = substr($color, 1);
        list($r, $g, $b) = array(hexdec($c[0].$c[1]), hexdec($c[2].$c[3]), hexdec($c[4].$c[5]));
        return 'rgba(' . $r . ',' . $g .  ',' . $b . ', 0.3)';
    }
}
