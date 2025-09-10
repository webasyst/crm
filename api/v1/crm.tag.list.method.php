<?php

class crmTagListMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    public function execute()
    {
        $contact_ids = wa()->getRequest()->get('contact_id', [], waRequest::TYPE_ARRAY_INT);
        $deal_ids = wa()->getRequest()->get('deal_id', [], waRequest::TYPE_ARRAY_INT);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        $deal_ids = crmHelper::dropNotPositive($deal_ids);
        $deal_ids = array_map(function ($id) { return -1 * $id; }, $deal_ids);

        $contact_ids = array_merge($contact_ids, $deal_ids);
        $tags_ids = empty($contact_ids) ? [] : array_unique(array_keys($this->getContactTagsModel()->getByField(['contact_id' => $contact_ids], 'tag_id')));
        
        if (!empty($contact_ids) && empty($tags_ids)) {
            $this->response = [];
            return;
        }

        $this->response = $this->prepareTags($this->getTagModel()->getCloud(null, null, [], $tags_ids));
    }
}