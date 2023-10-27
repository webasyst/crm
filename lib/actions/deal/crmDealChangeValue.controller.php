<?php

/**
 * Save data from inline editor on dealId page.
 */
class crmDealChangeValueController extends crmJsonController
{
    /**
     * @var crmDealParamsModel
     */
    protected $dpm;

    public function execute()
    {
        $deal_id    = waRequest::post('id', null, waRequest::TYPE_INT);
        $field_id   = waRequest::post('field_id', null, waRequest::TYPE_STRING_TRIM);
        $value      = waRequest::post('value', null, waRequest::TYPE_STRING_TRIM);
        $value_type = waRequest::post('value_type', null, waRequest::TYPE_STRING_TRIM);

        if (!$this->validate($value, $value_type)) {
            return false;
        }
        
        $deal_params = $this->getDealParams($field_id);
        if ($deal_params['value'] !== $value) {
            $has_field = $this->getDealParamsModel()->getByField(array(
                'deal_id' => $deal_id,
                'name' => $field_id
            ));
            if ($has_field) {
                $this->getDealParamsModel()->updateByField(array(
                    'deal_id' => $deal_id,
                    'name' => $field_id
                    ),
                    array(
                        'value' => $value
                    )
                );
            } else {
                $this->getDealParamsModel()->insert(array(
                    'deal_id' => $deal_id,
                    'name' => $field_id,
                    'value' => $value
                ));
            }

            $this->addLog($deal_id, $deal_params['value'], $value, $value_type);
        }
        switch ($value_type) {
            case 'date':
                $deal_params['value'] = !empty($value)? wa_date('date', $value) : '';
                break;
            case 'checkbox':
                $deal_field = new crmDealCheckboxField($deal_id, $field_id);
                $deal_params['value'] = $deal_field->format($value);
                break;
            default:
                $deal_params['value'] = $value;
                break;
        }
        $this->response = array(
            'deal_params' => $deal_params
        );
    }

    protected function addLog($deal_id, $value, $modified_value, $value_type)
    {
        $deal = $this->getDeal($deal_id);

        switch ($value_type) {
            case 'number':
                $value = $value ? (int)$value : null;
                $modified_value = $modified_value ? (int)$modified_value : null;
                break;
            case 'date':
                $value = $value ? wa_date('date', $value) : null;
                $modified_value = $modified_value ? wa_date('date', $modified_value) : null;
                break;
            case 'checkbox':
                $value = isset($value) ? (string)$value : null;
                $modified_value = isset($modified_value) ? (string)$modified_value : null;
                break;
        }

        $this->logAction(crmDealModel::LOG_ACTION_UPDATE, array('deal_id' => $deal_id));
        $lm = new crmLogModel();
        $lm->log(
            crmDealModel::LOG_ACTION_UPDATE,
            $deal['id'] * -1,
            $deal['id'],
            $value,
            $modified_value
        );
    }

    protected function validate(&$value, $value_type)
    {
        switch ($value_type) {
            case 'number':
                if ($value && !is_numeric($value)) {
                    $this->errors = array('number', 'Invalid value');
                    return false;
                }
                break;
            case 'date':
                if ($value && !strtotime($value)) {
                    $this->errors = array('date', 'Invalid date');
                    return false;
                }
                break;
        }

        return true;
    }

    public function getDealParams($field_id)
    {
        $id = (int)waRequest::post('id', null, waRequest::TYPE_STRING_TRIM);

        if (!$id) {
            $this->notFound();
        }
        $deal_params = $this->getDealParamsModel()->getByField(array('deal_id' => $id, 'name' => $field_id));

        $deal = $this->getDeal($id);
        if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }
        return $deal_params;
    }

    protected function getDealParamsModel()
    {
        return $this->dpm !== null ? $this->dpm : ($this->dpm = new crmDealParamsModel());
    }

    protected function getDeal($deal_id)
    {
        $dm = new crmDealModel();
        return $dm->getById($deal_id);
    }
}
