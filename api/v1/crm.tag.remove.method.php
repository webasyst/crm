<?php

class crmTagRemoveMethod extends crmApiAbstractMethod
{
    const CONTACT_LIMIT = 200;

    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $contact_ids = (array) ifset($_json, 'contact_id', []);
        $deal_ids = (array) ifset($_json, 'deal_id', []);
        $tags = (array) ifset($_json, 'tag', []);

        $this->http_status_code = 204;
        $this->response = null;

        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        $deal_ids = crmHelper::dropNotPositive($deal_ids);
        if (!empty($contact_ids) && !empty($deal_ids)) {
            throw new waAPIException('error', sprintf_wp('Only one of the parameters is required: %s.', sprintf_wp('“%s” or “%s”', 'contact_id', 'deal_id')), 400);
        }
        if ((empty($contact_ids) && empty($deal_ids)) || empty($tags)) {
            return;
        }

        if (!empty($contact_ids)) {
            $this->contactTags($contact_ids, $tags);
        } else {
            $this->dealTags($deal_ids, $tags);
        }
    }

    private function contactTags($contact_ids, $tags)
    {
        $cloud_tags = $this->getTagModel()->getCloud();
        foreach ($cloud_tags as $key => $_cloud_tag) {
            if (!in_array($_cloud_tag['name'], $tags)) {
                unset($cloud_tags[$key]);
            }
        }
        if (empty($cloud_tags)) {
            return;
        }

        $tag_model = $this->getTagModel();
        $options = ['check_rights' => true];
        $collection = new crmContactsCollection($contact_ids, $options);
        $contacts = $collection->getContacts('tags', 0, self::CONTACT_LIMIT);
        $remove_tags = array_column($cloud_tags, 'name');
        foreach ($contacts as $_contact) {
            $contact_tags = array_column(ifempty($_contact, 'tags', []), 'name');
            if (empty($contact_tags) || empty($_contact['id'])) {
                continue;
            }
            $tags_result = array_diff($contact_tags, $remove_tags);
            $tag_model->assign([$_contact['id']], $tags_result);
        }
    }

    private function dealTags($deal_ids, $tags)
    {
        $ids = [];
        foreach ($deal_ids as $id) {
            $ids[] = -$id;
        }
        $deal_tags = $this->getTagModel()->getByContact($ids, false);
        foreach ($ids as $_id) {
            $tags_result = array_diff(array_column($deal_tags[$_id], 'name'), $tags);
            $this->getTagModel()->assign([$_id], $tags_result, false);
        }
    }
}
