<?php
/**
 * Negative contact_ids in this table are deals.
 */
class crmContactTagsModel extends crmModel
{
    protected $table = 'crm_contact_tags';

    protected $unset_contact_links_behavior = 'delete';

    /**
     * @param int|array[] int $contact_id
     * @return bool
     */
    public function deleteByContact($contact_id, $drop_negative = true)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        if ($drop_negative) {
            $contact_ids = crmHelper::dropNotPositive($contact_ids);
        }
        return $this->deleteByField(array('contact_id' => $contact_ids));
    }

    /**
     * @param int|array[] int $contact_id
     * @return array
     */
    public function getByContact($contact_id, $drop_negative = true)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        if ($drop_negative) {
            $contact_ids = crmHelper::dropNotPositive($contact_ids);
        }
        return $this->getByField(array('contact_id' => $contact_ids), true);
    }
}
