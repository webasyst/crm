<?php

class crmFunnelSortSaveController extends waJsonController
{
    public function execute()
    {
        $ids = preg_split('/\s*,\s*/', $this->getRequest()->post('ids', null, waRequest::TYPE_STRING_TRIM));
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);
        $ids = array_unique($ids);

        if (empty($ids)) {
            return;
        }

        $unpinned_funnels = wa()->getUser()->getSettings('crm', 'unpinned_funnels');
        $unpinned_funnels = empty($unpinned_funnels) ? [] : explode(',', $unpinned_funnels);
        $ids = array_diff($ids, $unpinned_funnels);
        if (empty($ids)) {
            return;
        }

        wa()->getUser()->setSettings('crm', 'funnels_sort', array_unique($ids));
    }
}