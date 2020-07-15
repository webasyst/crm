<?php

class crmSettingsViewAction extends crmBackendViewAction
{
    public function __construct($params = null)
    {
        parent::__construct($params);

        $this->view->assign(array(
            'settings_template' => crmViewAction::getTemplate(),
            'is_admin' => wa()->getUser()->isAdmin('crm'),
            'shop_app_exists' => wa()->appExists('shop') && wa()->getUser()->getRights('shop', 'backend')
        ));
    }

    protected function getTemplate()
    {
        return 'templates/actions/settings/Settings.html';
    }
}
