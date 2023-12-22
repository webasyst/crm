<?php

class crmSettingsVaultDialogSaveController extends crmJsonController
{
    public function execute()
    {
        $vault_id = $this->getRequest()->post('id', null, waRequest::TYPE_INT);
        $data = $this->getRequest()->post('data', array(), waRequest::TYPE_ARRAY_TRIM);
        $groups = $this->getRequest()->post('groups', array(), waRequest::TYPE_ARRAY_TRIM);

        $this->validate($vault_id, $data);
        if ($this->errors) {
            return false;
        }
        $vm = new crmVaultModel();
        if ($vault_id) {
            $vm->updateById($vault_id, array(
                'name'  => $data['name'],
                'color' => ifset($data['color']),
            ));
        } else {
            $sort = $vm->select('MAX(sort) m')->fetchField('m') + 1;
            $vault_id = $vm->insert(array(
                'name'            => $data['name'],
                'color'           => ifset($data['color']),
                'create_datetime' => date('Y-m-d H:i:s'),
                'sort'            => $sort,
            ));
        }
        $crm = new waContactRightsModel();
        $gm = new waGroupModel();
        $group_ids = $to_delete = $gm->getNames();

        foreach ($groups as $id => $on) {
            if (isset($group_ids[$id])) {
                /*
                $crm->replace(array(
                    'group_id' => $id,
                    'app_id'   => 'crm',
                    'name'     => 'vault.'.$vault_id,
                    'value'    => 1,
                )); // ('*')->where("name='vault.$vault_id' AND group_id > 0")->fetchAll('group_id');
                */
                $crm->save($id * -1, 'crm', 'vault.'.$vault_id, 1);
                $crm->save($id * -1, 'crm', 'backend', 1);
                unset($to_delete[$id]);
            }
        }
        if ($to_delete) {
            foreach ($to_delete as $id => $r) {
                $crm->deleteByField(array(
                    'group_id' => $id,
                    'app_id'   => 'crm',
                    'name'     => 'vault.'.$vault_id,
                ));
            }
        }

        $this->response = array('id' => $vault_id);
        return true;
    }

    private function validate($vault_id, $data)
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $vm = new crmVaultModel();
        if ($vault_id && !($vault = $vm->getById($vault_id))) {
            throw new waException(_w('Vault not found.'));
        }
        if (empty($data['name'])) {
            $this->errors['name'] = _w('This field is required');
        }
    }
}
