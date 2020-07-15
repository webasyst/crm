<?php
class crmPbxUsersModel extends waModel
{
    protected $table = 'crm_pbx_users';

    public function getByContact($contact_id)
    {
        return $this->getByField(array(
            'contact_id' => $contact_id,
        ), true);
    }

    public function getPbxUsers()
    {
        $pbx_users = array();

        foreach($this->getAll() as $row) {
            $pbx_users[$row['plugin_id']][$row['plugin_user_number']][] = $row['contact_id'];
        }

        return $pbx_users;
    }
}
