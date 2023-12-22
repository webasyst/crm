<?php

class crmSettingsVaultDialogDeleteController extends crmJsonController
{
    public function execute()
    {
        $vault_id = $this->getRequest()->post('id', null, waRequest::TYPE_INT);

        $this->validate($vault_id);

        $vm = new crmVaultModel();
        $vm->deleteById($vault_id);

        $wcm = new waContactModel();
        $wcrm = new waContactRightsModel();
        $wcm->exec("UPDATE wa_contact SET crm_vault_id=0 WHERE crm_vault_id='$vault_id'");
        $wcrm->deleteByField(array('app_id' => 'crm', 'name' => 'vault.'.$vault_id));
    }

    private function validate($vault_id)
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $vm = new crmVaultModel();
        if (!$vault_id || !($vault = $vm->getById($vault_id))) {
            throw new waException(_w('Vault not found.'));
        }
    }
}
