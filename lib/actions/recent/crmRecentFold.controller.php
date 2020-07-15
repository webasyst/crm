<?php

class crmRecentFoldController extends crmJsonController
{
    public function execute()
    {
        $fold_hidden = waRequest::post('fold_hidden', null, waRequest::TYPE_INT);

        if ($fold_hidden == 1) {
            wa()->getUser()->setSettings('crm', 'sidebar_recent_block_hidden', '1');
        } else {
            wa()->getUser()->delSettings('crm', 'sidebar_recent_block_hidden');
        }
    }
}
