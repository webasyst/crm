<?php

/**
 * Class tasksUpgradeAction
 */
class crmUpgradeAction extends crmBackendViewAction
{
    public function execute()
    {
        $this->view->assign([
            'is_premium' => crmHelper::isPremium(),
        ]);
    }
}