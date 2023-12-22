<?php

class crmSettingsVaultDialogAction extends waViewAction
{
    public function execute()
    {
        $vault_id = $this->getRequest()->post('id', null, waRequest::TYPE_INT);

        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $vm = new crmVaultModel();
        if ($vault_id && !($vault = $vm->getById($vault_id))) {
            throw new waException(_w('Vault not found.'));
        }
        $groups = crmHelper::getAvailableGroups('vault.'.$vault_id, true);

        $this->view->assign(array(
            'vault'  => ifset($vault),
            'groups' => $groups,
        ));
    }
}
