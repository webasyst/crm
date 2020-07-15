<?php
/**
 * Dialog HTML to add a client contact to an existing deal
 */
class crmDealAddParticipantAction extends crmBackendViewAction
{
    public function execute()
    {
        $dm = new crmDealModel();
        $deal = $dm->getById($this->getDealId());
        if (!$deal) {
            $this->notFound();
        }

        // Check access rights
        if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }

        $this->view->assign(array(
            'deal' => $deal
        ));
    }

    protected function getDealId()
    {
        return (int) $this->getRequest()->request('id');
    }
}
