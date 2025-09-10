<?php

class crmSettingsTemplatesSaveController extends crmJsonController
{
    public function execute()
    {
        $get_data = waRequest::post('data', array(), waRequest::TYPE_ARRAY_TRIM);

        $data = $this->validate($get_data);

        $id = $this->saveData($data);

        $this->response = array(
            'id' => $id
        );
    }

    /**
     * @param $get_data
     * @return array
     * @throws waRightsException
     */
    protected function validate($get_data)
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $arrFields = ['id' => '', 'name' => '', 'content' => '', 'origin_id' => '', 'style_version' => '', 'param_name' => '', 'param_type' => '', 'param_placeholder' => '', 'param_code' => ''];

        $data = array_intersect_key($get_data, $arrFields);

        foreach ($arrFields as $key => $item) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = null;
            }
        };

        return $data;
    }

    /**
     * @param $data
     * @return bool|int|resource
     * @throws waException
     */
    protected function saveData($data)
    {
        $tm = new crmTemplatesModel();
        $tpm = new crmTemplatesParamsModel();

        if (empty($data['id'])) {
            $ins = [
                'name'    => $data['name'],
                'content' => $data['content'],
                'origin_id' => $data['origin_id'],
                'style_version' => $data['style_version'],
            ];

            $data['id'] = $tm->insert($ins);

            $this->setParams($data['id'], $data['param_name'], $data['param_code'], $data['param_type'], $data['param_placeholder']);

        } else {
            $old_template = $tm->getById($data['id']);

            if (!empty($data['id']) && !$old_template) {
                throw new waException('Template not found');
            }

            //deleting images for renamed variables
            $this->deleteImages($data);

            //drop_old_params
            $tpm->deleteByField('template_id', $data['id']);

            $this->setParams($data['id'], $data['param_name'], $data['param_code'], $data['param_type'], $data['param_placeholder']);

            if ($data['name'] !== $old_template['name'] || $data['content'] !== $old_template['content']) {
                $tm->updateById($data['id'], [
                    'name'    => $data['name'],
                    'content' => $data['content']
                ]);
            }
        }
        return $data['id'];
    }

    /**
     * @param $id
     * @param $param_name
     * @param $param_code
     * @param $param_type
     * @param $param_placeholder
     * @return bool
     */
    private function setParams($id, $param_name, $param_code, $param_type, $param_placeholder)
    {
        if (!$id || !$param_name || !$param_type) {
            return false;
        }

        $tpm = new crmTemplatesParamsModel();

        $i = 1;
        foreach (array_map(null, $param_name, $param_code, $param_type, $param_placeholder) as $arr_params) {
            list($name, $code, $type, $placeholder) = $arr_params;

            if ($code && $name) {
                $tpm->insert(array(
                    'template_id' => $id,
                    'code'        => $code,
                    'name'        => $name,
                    'type'        => $type,
                    'sort'        => $i,
                    'placeholder' => $placeholder
                ));
            } else {
                continue;
            }

            $i++;
        }

        $_GET['invoice_template_id'] = $id;
        $action = new webasystBackendCheatSheetActions();
        $action->updateAppCacheConfig(waSystem::getApp());
    }

    /**
     * Get old settings and compare with new ones
     * @param $data
     */
    protected function deleteImages($data)
    {
        $tpm = new crmTemplatesParamsModel();
        $cpm = new crmCompanyParamsModel();

        $old_params = $tpm->getParamsByTemplates($data['id']);

        foreach ($old_params as $code => $value) {
            if ($value['type'] == 'IMAGE' && !in_array($code, $data['param_code'])) {
                crmCompanyImageHandler::deleteTemplatesImage($data['id'], $code);
                $cpm->deleteByField(array(
                    'template_id' => $data['id'],
                    'name'        => $code
                ));
            }
        }
    }
}
