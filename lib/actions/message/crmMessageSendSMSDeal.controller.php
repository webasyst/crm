<?php

/**
 * Class crmMessageSendSMSDealController
 *
 * Send SMS from Deal page
 */
class crmMessageSendSMSDealController extends crmSendSMSController
{
    /**
     * @var array
     */
    protected $deal;

    public function getData()
    {
        $data = parent::getData();
        $deal = $this->getDeal();
        $data['deal_id'] = $deal['id'];
        return $data;
    }

    public function prepareMessageToFix($data)
    {
        $message = parent::prepareMessageToFix($data);
        $message['deal_id'] = $data['deal_id'];
        return $message;
    }

    protected function getDeal()
    {
        if ($this->deal !== null) {
            return $this->deal;
        }

        $id = (int)$this->getRequest()->post('deal_id');
        if ($id <= 0) {
            $this->notFound();
        }
        $deal = $this->getDealModel()->getDeal($id, true);
        if (!$deal) {
            $this->notFound();
        }

        if (!$this->getCrmRights()->deal($deal)) {
            $this->accessDenied();
        }

        $deal = $this->getDealModel()->getDeal($id);

        return $this->deal = $deal;
    }
}
