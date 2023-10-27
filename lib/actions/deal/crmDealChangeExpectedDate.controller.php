<?php
/**
 * Saves data from inline editor on a DealId page
 */
class crmDealChangeExpectedDateController extends crmJsonController
{
    /**
     * @var crmDealModel
     */
    protected $dm;

    public function execute()
    {
        $expected_date = waRequest::post('expected_date', null, waRequest::TYPE_STRING_TRIM);

        if (!$this->validate($expected_date)) {
            return false;
        }
        $deal = $this->getDeal();

        if ($deal['expected_date'] !== $expected_date) {
            if (!$expected_date) {
                $expected_date = null;
            }
            $this->getDealModel()->updateById($deal['id'], array('expected_date' => $expected_date));

            $this->logAction(crmDealModel::LOG_ACTION_UPDATE, array('deal_id' => $deal['id']));
            $lm = new crmLogModel();
            $lm->log(
                crmDealModel::LOG_ACTION_UPDATE,
                $deal['id'] * -1,
                $deal['id'],
                $deal['expected_date'] ? wa_date('date', $deal['expected_date']) : null,
                $expected_date ? wa_date('date', $expected_date) : null
            );
            $deal['expected_date'] = $expected_date ? wa_date('date', $expected_date) : null;
        } else {
            $deal['expected_date'] = $deal['expected_date'] ? wa_date('date', $deal['expected_date']) : null;
        }
        $this->response = array(
            'deal' => $deal
        );
    }

    protected function validate($expected_date)
    {
        if ($expected_date && !strtotime($expected_date)) {
            $this->errors = array('expected_date', 'Invalid date');
            return false;
        }
        return true;
    }

    public function getDeal()
    {
        $id = (int)$this->getRequest()->request('id');
        if (!$id) {
            $this->notFound();
        }
        $deal = $this->getDealModel()->getById($id);
        if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }
        return $deal;
    }

    /**
     * @return crmDealModel
     */
    protected function getDealModel()
    {
        return $this->dm !== null ? $this->dm : ($this->dm = new crmDealModel());
    }
}
