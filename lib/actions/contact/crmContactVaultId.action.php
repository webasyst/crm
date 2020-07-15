<?php

class crmContactVaultIdAction extends crmContactsAction
{
    protected $id;
    protected $available_vault_ids;

    protected function afterExecute()
    {
        $info = $this->getCollection()->getInfo();
        $vault = ifset($info['vault']);
        if (!$vault) {
            if (is_array($this->id)) {
                $vault = array(
                    'id' => '',
                    'name' => _w('My own'),
                );
            } else {
                $this->notFound();
            }
        }
        $this->view->assign(array(
            'vault' => $vault,
            'title' => $vault['name'],
            'can_edit' => wa()->getUser()->isAdmin('crm'),
        ));
    }

    protected function getHash()
    {
        $vault_id = $this->getVaultId();
        if (is_array($vault_id)) {
            return "vault/".join(',', $vault_id);
        } else {
            return "vault/".$vault_id;
        }
    }

    protected function getVaultId()
    {
        if ($this->id !== null) {
            return $this->id;
        }

        $id = $this->getParameter('id');
        if ($id == 'own') {
            $this->id = $this->getOwnVaultIds();
        } else {
            $this->id = (int) $id;
            if (!wa()->getUser()->isAdmin('crm') && !in_array($this->id, $this->getCrmRights()->getAvailableVaultIds())) {
                $this->notFound();
            }
        }

        return $this->id;
    }
}
