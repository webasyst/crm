<?php
/**
 * Represents an intermediate entity for access rights control for individual contacts.
 *
 * Overall, access control structure for contacts goes like this.
 * - Each contact has an int (not null) field `wa_contact.crm_vault_id`.
 * - crm_vault_id == 0 means all backend users have read and write access to this contact.
 * - crm_vault_id > 0 is a `crm_vault.id`. Access to vaults is set up via user access dialog
 *   on Access tab in user profile. Users who have access to vault can read and write contacts
 *   assigned to that vault. Everyone else have no access.
 * - crm_vault_id < 0 represents a `crm_adhoc_group.adhoc_id` from this table. Only users specifically
 *   listed for particular adhoc_id can read and write such contacts. Everyone else have no access.
 *
 * Web-interface to set up crm_vault_id < 0 does not mention 'adhoc groups'.
 * This is an implementation detail, optimizing SQL queries. From UI point of view
 * admin can set a list of users who have access to contact. An adhoc group
 * is transparently maintained in the background to support this.
 */
class crmAdhocGroupModel extends crmModel
{
    protected $table = 'crm_adhoc_group';

    public function getByContact($contact_id)
    {
        return array_keys($this->select('adhoc_id')->where('contact_id IN (?)', $contact_id)->fetchAll('adhoc_id'));
    }

    public function getByGroup($group_id)
    {
        return array_keys($this->select('contact_id')->where('adhoc_id IN (?)', $group_id)->fetchAll('contact_id'));
    }

    /**
     * Change crm_vault_id for a group of contacts that currently
     * - either all belong to a vault or are free (crm_vault_id >= 0),
     * - or all share the same crm_vault_id < 0.
     *
     * @param $contact_ids          array       list of contact_ids to change crm_vault_id
     * @param $old_adhoc_group_id   int|null    old negated value of crm_vault_id, if crm_vault_id < 0; null if >= 0.
     * @param $vault_id             int         vault to assign contacts to
     */
    public function setContactsVault($contact_ids, $old_adhoc_group_id, $vault_id)
    {
        // Sanitize input
        $contact_ids = array_filter(array_map('intval', $contact_ids));
        $contact_ids = array_keys(array_flip($contact_ids));
        if (!$contact_ids) {
            return;
        }

        $this->writeLock();

        // delete old adhoc group if there are no more contacts there
        if ($old_adhoc_group_id) {
            $old_group_count = $this->countContactsByGroup($old_adhoc_group_id);
            if ($old_group_count <= count($contact_ids)) {
                $this->deleteByField('adhoc_id', $old_adhoc_group_id);
            }
        }

        // Update contact
        $this->exec("UPDATE wa_contact SET crm_vault_id=? WHERE id IN (?)", (int)$vault_id, $contact_ids);

        $this->unlockAll();
    }

    /**
     * Change crm_vault_id for a group of contacts that currently
     * - either all belong to a vault or are free (crm_vault_id >= 0),
     * - or all share the same crm_vault_id < 0.
     *
     * @example assign owner of `$contact` to current user logged in
     * $adhoc_group_model->setContactsOwners($contact['id'], -$contact['crm_vault_id'], wa()->getUser()->getId())
     *
     * @example assign owner of several contacts `$contact_ids` to two users `$user_ids`
     * All $contact_ids must share the same $crm_vault_id = wa_contact.crm_vault_id
     * $adhoc_group_model->setContactsOwners($contact_ids, -$crm_vault_id, $user_ids)
     *
     * @param $contact_ids          int|array   single contact_id or list of contact_ids to change crm_vault_id
     * @param $old_adhoc_group_id   int|null    old negated value of crm_vault_id, if crm_vault_id < 0; null if >= 0.
     * @param $new_owners           array       list of contact_ids of users
     */
    public function setContactsOwners($contact_ids, $old_adhoc_group_id, $new_owners)
    {
        // Sanitize and validate
        $contact_ids = array_filter(array_map('intval', (array)$contact_ids));
        $contact_ids = array_keys(array_flip($contact_ids));
        if (!$contact_ids) {
            return;
        }
        $new_owners = array_filter(array_map('intval', (array)$new_owners));
        $new_owners = array_keys(array_flip($new_owners));
        if (!$new_owners) {
            throw new waException('Unable to assign contacts to an empty group of users');
        }

        $this->readLock();

        // Find an existing adhoc_group with given set of users, if any. Reurns int or null.
        $new_adhoc_group_id = $this->findByOwners($new_owners);

        // Nothing to do if new matches old
        if ($old_adhoc_group_id && $new_adhoc_group_id && $old_adhoc_group_id == $new_adhoc_group_id) {
            $this->unlockAll();
            return;
        }

        $this->writeLock();

        // Count contacts that belong to old adhoc_group_id
        $no_more_contacts_in_old_group = $old_adhoc_group_id && count($contact_ids) >= $this->countContactsByGroup($old_adhoc_group_id);

        // In case there is no existing adhoc group
        // with given set of users, we need to make one.
        if (!$new_adhoc_group_id) {

            $user_ids = $new_owners;

            // If old group exists but contains no other contacts,
            // then reuse its id. Otherwise, create a new adhoc group.
            if ($old_adhoc_group_id && $no_more_contacts_in_old_group) {
                $this->deleteByField('adhoc_id', $old_adhoc_group_id);
                $new_adhoc_group_id = $old_adhoc_group_id;
            } else {
                // Use auto-increment to generate new adhoc_id
                $new_adhoc_group_id = $this->query("INSERT INTO {$this->table} SET contact_id=?", array_pop($user_ids))->lastInsertId();
            }

            if ($user_ids) {
                $this->multipleInsert(array(
                    'adhoc_id' => $new_adhoc_group_id,
                    'contact_id' => $user_ids,
                ));
                unset($user_ids);
            }
        }

        // Update contact
        if ($new_adhoc_group_id != $old_adhoc_group_id) {
            // delete old adhoc group if there are no more contacts there
            if ($old_adhoc_group_id && $no_more_contacts_in_old_group) {
                $this->deleteByField('adhoc_id', $old_adhoc_group_id);
            }

            // Update contact
            $this->exec("UPDATE wa_contact SET crm_vault_id=? WHERE id IN (?)", -$new_adhoc_group_id, $contact_ids);
        }

        $this->unlockAll();
    }

    protected function findByOwners($owner_ids)
    {
        sort($owner_ids);
        $sql = "SELECT adhoc_id, GROUP_CONCAT(contact_id ORDER BY contact_id SEPARATOR ',') AS contacts
                FROM {$this->table}
                GROUP BY adhoc_id
                HAVING contacts=?";
        $result = $this->query($sql, join(',', $owner_ids))->fetchField('adhoc_id');
        return ifempty($result);
    }

    protected function countContactsByGroup($adhoc_group_id)
    {
        return $this->query("SELECT COUNT(*) FROM wa_contact WHERE crm_vault_id=?", -$adhoc_group_id)->fetchField();
    }

    protected function readLock()
    {
        $this->exec(
            "LOCK TABLES
                {$this->table} READ,
                wa_contact READ"
        );
    }

    protected function writeLock()
    {
        $this->exec(
            "LOCK TABLES
                {$this->table} WRITE,
                wa_contact WRITE"
        );
    }

    protected function unlockAll()
    {
        $this->exec("UNLOCK TABLES");
    }
}
