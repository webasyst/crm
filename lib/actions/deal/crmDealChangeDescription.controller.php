<?php

/**
 * Save data from inline editor on dealId page.
 */
class crmDealChangeDescriptionController extends crmJsonController
{
    /**
     * @var crmDealParamsModel
     */
    protected $dpm;

    public function execute()
    {
        $deal_id = waRequest::post('id', null, waRequest::TYPE_INT);
        $value   = waRequest::post('value', null, waRequest::TYPE_STRING_TRIM);

        $dm = new crmDealModel();
        $deal = $this->getDeal($deal_id);
        $dm->updateByField('id', $deal_id, array('description' => $value));

        $this->addLog($deal_id, $deal['description'], $value);

        $deal_description['value'] = !empty($value) ? crmHtmlSanitizer::work($value) : '';

        $this->response = array(
            'deal_description' => $deal_description
        );
    }

    protected function addLog($deal_id, $value, $modified_value)
    {
        $deal = $this->getDeal($deal_id);
        $this->logAction(crmDealModel::LOG_ACTION_UPDATE, array('deal_id' => $deal_id));
        $lm = new crmLogModel();
        $lm->log(
            crmDealModel::LOG_ACTION_UPDATE,
            $deal['id'] * -1,
            $deal['id'],
            null,
            null
        );
    }

    protected function getDeal($deal_id)
    {
        $dm = new crmDealModel();
        return $dm->getById($deal_id);
    }
}
