<?php

class crmFunnelPinController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', null, waRequest::TYPE_INT);
        $state = waRequest::post('state', null, waRequest::TYPE_STRING_TRIM);
        if (empty($id) || empty($state) || !in_array($state, ['pin', 'unpin'])) {
            return;
        }
        
        $unpinned_funnels = wa()->getUser()->getSettings('crm', 'unpinned_funnels');
        $unpinned_funnels = empty($unpinned_funnels) ? [] : explode(',', $unpinned_funnels);
        if ($state == 'pin') {
            $unpinned_funnels = array_diff($unpinned_funnels, [$id]);
        } else {
            $unpinned_funnels[] = $id;
        }
        wa()->getUser()->setSettings('crm', 'unpinned_funnels', array_unique($unpinned_funnels));
    }
}