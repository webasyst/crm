<?php

class crmSettingsCompaniesSaveController extends crmJsonController
{
    /**
     * @object waRequestFile
     */
    private $logo_file;

    /**
     * @object waRequestFile
     */
    private $images;

    /**
     * @array $_POST company
     */
    private $company;

    public function execute()
    {
        $this->company = waRequest::post('company', array(), waRequest::TYPE_ARRAY_TRIM);
        $this->images = waRequest::file('images');
        $this->logo_file = waRequest::file('logo');
        $cm = new crmCompanyModel();


        $this->company = $this->validate($this->company);

        //If company new, create company
        if (!$this->company['id']) {
            $this->company['id'] = $cm->insert($this->company);
            $this->insertCompanyParams();
        }

        $this->company['invoice_options'] = $this->updateCompanyParams();

        //Check $_FILES
        if ($this->logo_file->count()) {
            $this->savePhoto($this->logo_file, 'logo');
            $this->company['logo'] = pathinfo($this->logo_file->name, PATHINFO_EXTENSION);
        }

        if ($this->images->count()) {
            $this->savePhoto($this->images, 'param_image');
        }

        if (!$this->errors && $this->company) {
            $cm->updateById($this->company['id'], $this->company);
            $this->insertCompanyParams();
        }

        $this->response = array('id' => $this->company['id']);
    }

    protected function insertCompanyParams()
    {
        $cpm = new crmCompanyParamsModel();
        if (isset($this->company['invoice_options'])) {
            $cpm->deleteByField(array(
                'company_id'  => $this->company['id'],
                'template_id' => $this->company['template_id'],
            ));

            $insert_params = array();
            foreach ($this->company['invoice_options'] as $key => $value) {
                $insert_params[] = array(
                    'company_id'  => $this->company['id'],
                    'template_id' => $this->company['template_id'],
                    'name'        => $key,
                    'value'       => $value
                );
            }
            $cpm->multipleInsert($insert_params);
        }
    }

    /**
     * Save photo to hard disk
     * @param waRequestFile $files
     * @param $type string 'logo' or 'param_image'
     * @return bool
     */
    protected function savePhoto($files, $type)
    {
        foreach ($files as $key => $file) {
            $ext = pathinfo($file->name, PATHINFO_EXTENSION);
            $cdi = new crmCompanyImageHandler(array(
                'company_id'  => $this->company['id'],
                'type'        => $type,
                'ext'         => $ext,
                'code'        => $key,
                'template_id' => $this->company['template_id'],
            ));
            if ($cdi->saveImage($file)) {
                $this->company['invoice_options'][$key] = $ext;
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Retrieves the basic parameters of the template.
     * Checks each parameter for the type "IMAGE". If true, then it checks whether the request to delete the picture came.
     * Then add images that have not been changed to basic
     * @return array
     */
    protected function updateCompanyParams()
    {
        // Fetch old company invoice params to keep images we don't need to delete
        $cpm = new crmCompanyParamsModel();
        $old_company = $cpm->getParams($this->company['id'], $this->company['template_id']);

        // New invoice params
        $new_company = array();

        // Delete old (updated/removed) images and save list of non-removed images into $new_company
        $tpm = new crmTemplatesParamsModel();
        $params_description = $tpm->getByField('template_id', $this->company['template_id'], 'code');
        foreach ($params_description as $code => $p) {
            $new_company[$code] = null;
            if ($p['type'] == 'IMAGE' && isset($old_company[$code])) {

                // Delete old image if asked to
                if (isset($this->company['invoice_options']['images'][$code])) {
                    $cdi = new crmCompanyImageHandler(array(
                        'company_id'  => $this->company['id'],
                        'type'        => 'param_image',
                        'code'        => $code,
                        'template_id' => $this->company['template_id'],
                        'ext'         => $old_company[$code],
                    ));
                    $cdi->deleteImage();
                } else {
                    $new_company[$code] = ifset($old_company[$code]); // Otherwise keep old image in new params
                }
            } else {
                $new_company[$code] = ifset($this->company['invoice_options'][$code]);
            }
        }

        $params_description['domain'] = true;
        $new_company['domain'] = ifset($this->company['invoice_options']['domain']);

        unset($this->company['invoice_options']['images']);

        // Make sure there are no unknown keys in params
        $new_company = array_intersect_key($new_company, $params_description);

        return $new_company;
    }

    /**
     * @param $company
     * @return mixed
     * @throws waRightsException
     */
    private function validate($company)
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        // Make sure all keys are present in array
        $allowed_keys = array(
            'id',
            'name',
            'phone',
            'address',
            'tax_name',
            'tax_options',
            'invoice_template_id',
            'invoice_options',
            'template_id',
            'logo',
        );
        foreach ($allowed_keys as $key) {
            $out[$key] = ifset($company[$key]);
        }

        if (!$out['name']) {
            $this->errors[] = array('name', _w('This field is required'));
            return $out;
        }
        $tax_options = ifempty($out['tax_options'], array());
        if ($tax_options && empty($out['tax_name'])) {
            $this->errors[] = array('tax_name', _w('This field is required'));
            return $out;
        }

        $_i = 0;
        foreach ($tax_options as &$t) {
            if (!empty($t['tax_percent'])) {
                $t['tax_percent'] = str_replace(',', '.', $t['tax_percent']);
                if (!is_numeric($t['tax_percent']) || $t['tax_percent'] < 0 || $t['tax_percent'] > 100) {
                    $this->errors[] = array(
                        "name"  => 'company[tax_options]['.$_i.'][tax_percent]',
                        "value" => _w('Invalid tax percent')
                    );
                }
                $_i = $_i + 1;
            }
        }
        unset($t);
        $tax_options = $tax_options ? json_encode($tax_options) : null;
        $out['tax_options'] = $tax_options;

        if (!empty($company['invoice_options']['color'])) {

            if (!preg_match('/^#[0-9a-h]{3,6}$/', $company['invoice_options']['color'])) {
                $this->errors[] = array(
                    "name"  => 'company[invoice_options][color]',
                    "value" => _w('Invalid color')
                );
            }
        }

        $tpm = new crmTemplatesParamsModel();
        $template_params = $tpm->getParamsByTemplates($out['template_id']);
        foreach ($template_params as $param_id => $param) {
            if (isset($out['invoice_options'][$param_id])
                && $param['type'] == crmTemplatesModel::PARAM_TYPE_STRING
                && mb_strlen($out['invoice_options'][$param_id]) > 255
            ) {
                $this->errors[] = array(
                    "name"  => "company[invoice_options][$param_id]",
                    "value" => _w('Entered text is too long. Shorten it to maximum 255 characters.'),
                );
            }
        }

        //Check logo
        if ($this->logo_file->count() && $out['logo'] == 'delete') {
            unset($out['logo']);
        } elseif ($out['logo'] == 'delete') {
            $cm = new crmCompanyModel();
            $old_company = $cm->getById($this->company['id']);
            $cdi = new crmCompanyImageHandler(array(
                'company_id' => $this->company['id'],
                'type'       => 'logo',
                'ext'        => $old_company['logo']
            ));
            $cdi->deleteImage();
            $out['logo'] = null;
        } else {
            unset($out['logo']);
        }

        return $out;
    }
}
