<?php

class crmDealExportAction extends crmBackendViewAction
{
    public function execute()
    {
        $ids = $this->getIds();
        $allowed_ids = $this->dropUnallowed($ids);

        $count = count($allowed_ids);

        $is_ui13 = (wa()->whichUI('crm') === '1.3');
        if (!$is_ui13) {
            $this->setLayout();
        }

        $this->view->assign(array(
            'ids' => $allowed_ids,
            'count' => $count,
            'dropped_ids_count' => count($ids) - $count,
            'encoding' => crmHelper::getImportExportEncodings()
        ));
    }

    protected function getIds()
    {
        $ids = waRequest::request('ids', [], waRequest::TYPE_ARRAY_INT);
        $ids = crmHelper::toIntArray($ids);
        return crmHelper::dropNotPositive($ids);
    }

    /**
     * @param int[] $ids
     * @return int[]
     * @throws waDbException
     * @throws waException
     */
    protected function dropUnallowed($ids)
    {
        return $this->getCrmRights()->dropUnallowedDeals($ids, [
            'level' => crmRightConfig::RIGHT_DEAL_ALL
        ]);
    }
}
