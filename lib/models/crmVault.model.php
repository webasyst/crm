<?php
class crmVaultModel extends crmModel
{
    protected $table = 'crm_vault';

    public function getCounts()
    {
        $cnts = $this->select('id,count')->fetchAll('id', 'true');
        if (!$cnts) {
            return array();
        }

        $sql = "SELECT count(*) cnt, crm_vault_id FROM wa_contact WHERE crm_vault_id > 0 GROUP BY crm_vault_id";
        $result = $this->query($sql)->fetchAll('crm_vault_id', true);
        foreach($cnts as $id => $cnt) {
            $result[$id] = ifset($result[$id], '0');
            if ($result[$id] != $cnt) {
                $this->updateCount($id, $result[$id]);
            }
        }

        return $result;
    }

    // Called by contacts collection
    public function updateCount($id, $cnt)
    {
        $this->updateById($id, array(
            'count' => $cnt,
        ));
    }

    /**
     * List of vaults visible to user, according to access rights.
     * @param $user int|waContact defaults to current user logged in
     * @return array id => db row
     */
    public function getAvailable($user = null)
    {
        if (!$user) {
            $user = wa()->getUser();
        } else if (!$user instanceof waContact) {
            $user = new waContact($user);
        }

        $query = $this->select('*')->order('sort');
        if (!$user->isAdmin('crm')) {
            $vault_ids = array_keys($user->getRights('crm', 'vault.%'));
            if (!$vault_ids) {
                return array();
            }
            $query->where('id IN (?)', array($vault_ids));
        }

        return $query->fetchAll('id');
    }
}
