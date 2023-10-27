<?php

class crmContactRecentMethod extends crmApiAbstractMethod
{
    const USERPIC_SIZE = 32;
    const RECENT_LIMIT = 10;

    public function execute()
    {
        $recent = [];
        $pinned = [];
        $userpic_size = waRequest::get('userpic_size', self::USERPIC_SIZE, waRequest::TYPE_INT);
        $recent_limit = waRequest::get('limit', self::RECENT_LIMIT, waRequest::TYPE_INT);
        $userpic_size = ifempty($userpic_size, self::USERPIC_SIZE);
        $recent_limit = ifempty($recent_limit, self::RECENT_LIMIT);
        $all_recent = $this->getRecentModel()->select('user_contact_id, contact_id, is_pinned')
            ->where('user_contact_id = ?', $this->getUser()->getId())
            ->where('contact_id > 0')
            ->order('is_pinned DESC, view_datetime DESC')
            ->fetchAll('contact_id');

        $collection = new waContactsCollection('/id/'.join(',', array_keys($all_recent)));
        $contacts = $collection->getContacts('name,firstname,lastname,middlename,photo');
        foreach ($all_recent as $_id => $_contact) {
            if (empty($contacts[$_id])) {
                continue;
            }
            if (!empty($_contact['is_pinned'])) {
                $pinned[$_id] = $contacts[$_id];
            } elseif ($recent_limit) {
                $recent[$_id] = $contacts[$_id];
                $recent_limit--;
            } else {
                $this->getRecentModel()->deleteByField($_contact);
            }
        }

        $this->response = [
            'recent' => $this->prepareContactsList(
                $recent,
                ['id', 'name', 'userpic'],
                $userpic_size
            ),
            'pinned' => $this->prepareContactsList(
                $pinned,
                ['id', 'name', 'userpic'],
                $userpic_size
            )
        ];
    }
}
