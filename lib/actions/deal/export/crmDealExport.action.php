<?php

class crmDealExportAction extends crmBackendViewAction
{
    public function execute()
    {
        $ids = $this->getIds();
        $allowed_ids = $this->dropUnallowed($ids);

        $count = count($allowed_ids);

        $this->view->assign(array(
            'ids' => $allowed_ids,
            'count' => $count,
            'dropped_ids_count' => count($ids) - $count,
            'encoding' => crmHelper::getImportExportEncodings()
        ));
    }

    protected function getIds()
    {
        $ids = $this->getRequest()->post('ids');
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
