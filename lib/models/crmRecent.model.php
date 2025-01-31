<?php

class crmRecentModel extends crmModel
{
    protected $table = 'crm_recent';

    protected $link_contact_field = array('contact_id', 'user_contact_id');

    protected $unset_contact_links_behavior = array(
        'contact_id' => 'delete',
        'user_contact_id' => 'delete'
    );

    public function update($contact_id)
    {
        $user_contact_id = wa()->getUser()->getId();
        $dt = date('Y-m-d H:i:s');
        $sql = "INSERT INTO {$this->getTableName()} SET
            user_contact_id = ".(int)$user_contact_id.",
            contact_id = ".(int)$contact_id.",
            view_datetime = '$dt'
            ON DUPLICATE KEY UPDATE view_datetime = '$dt'";
        return $this->exec($sql);
    }

    public function pin($contact_id)
    {
        $user_contact_id = wa()->getUser()->getId();
        $dt = date('Y-m-d H:i:s');
        $sql = "INSERT INTO {$this->getTableName()} SET
            user_contact_id = ".(int)$user_contact_id.",
            contact_id = ".(int)$contact_id.",
            is_pinned = 1,
            view_datetime = '$dt'
            ON DUPLICATE KEY UPDATE is_pinned = 1";
        return $this->exec($sql);
    }

    public function getContactLinksCount($contact_id)
    {
        return 0;
    }
}
