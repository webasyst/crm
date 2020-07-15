<?php

class crmSettingsVaultsSaveController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $vaults = $this->getRequest()->post('vaults', array(), waRequest::TYPE_ARRAY_TRIM);

        $vm = new crmVaultModel();
        $sort = 0;
        foreach ($vaults as $id) {
            if ($vm->getById($id)) {
                $vm->updateById($id, array('sort' => $sort++));
            }
        }
    }
}
