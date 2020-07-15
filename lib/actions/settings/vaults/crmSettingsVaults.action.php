<?php

class crmSettingsVaultsAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $vm = new crmVaultModel();
        $vaults = $vm->select('*')->order('sort')->fetchAll('id');
        $this->view->assign(array(
            'vaults' => $vaults,
        ));
    }
}
