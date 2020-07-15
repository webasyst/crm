<?php

class crmSettingsCronAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $this->view->assign(array(
            'root_path' => $this->getConfig()->getRootPath() . DIRECTORY_SEPARATOR
        ));
    }
}
