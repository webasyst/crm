<?php

class crmNoteModel extends crmModel
{
    protected $table = 'crm_note';

    protected $link_contact_field = array('contact_id', 'creator_contact_id');

    protected $unset_contact_links_behavior = array('contact_id' => 'delete');

    /**
     * @param int|array[]int $contact_id
     */
    public function deleteByContact($contact_id)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (!$contact_ids) {
            return;
        }
        $this->deleteByField('contact_id', $contact_ids);
    }

}
