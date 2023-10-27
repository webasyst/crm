<?php

/**
 * Save data from inline editor on dealId page.
 */
class crmDealChangeAmountController extends crmJsonController
{
    /**
     * @var crmDealModel
     */
    protected $dm;

    public function execute()
    {
        $amount = waRequest::post('amount', null, waRequest::TYPE_STRING_TRIM);
        $currency_id = waRequest::post('currency_id', null, waRequest::TYPE_STRING_TRIM);

        if (!$this->validate($amount, $currency_id)) {
            return false;
        }
        $deal = $this->getDeal();

        if ($deal['amount'] !== $amount || $deal['currency_id'] !== $currency_id) {
            $cm = new crmCurrencyModel();
            $currency = $cm->get($currency_id);

            $this->getDealModel()->updateById($deal['id'], array(
                'amount'        => $amount,
                'currency_id'   => $currency_id,
                'currency_rate' => $currency['rate'],
            ));

            $this->logAction(crmDealModel::LOG_ACTION_UPDATE, array('deal_id' => $deal['id']));
            $lm = new crmLogModel();
            if ($deal['amount'] !== $amount) {
                $lm->log(
                    crmDealModel::LOG_ACTION_UPDATE,
                    $deal['id'] * -1,
                    $deal['id'],
                    $deal['amount'] ? waCurrency::format('%{s}', $deal['amount'], $deal['currency_id']) : null,
                    $amount ? waCurrency::format('%{s}', $amount, $currency_id) : null
                );
            }
            $deal['amount'] = $amount ? waCurrency::format('%{s}', $amount, $currency_id) : null;
            $deal['currency_id'] = $currency_id;
        }
        $this->response = array(
            'deal' => $deal
        );
    }

    protected function validate(&$amount, $currency_id)
    {
        $amount = str_replace(',', '.', $amount);
        if ($amount && !is_numeric($amount)) {
            $this->errors = array('amount', 'Invalid amount');
            return false;
        }
        $m = new crmCurrencyModel();
        $currencies = $m->getAll('code');
        if (empty($currencies[$currency_id])) {
            $this->errors = array('currency_id', 'Invalid currency');
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
