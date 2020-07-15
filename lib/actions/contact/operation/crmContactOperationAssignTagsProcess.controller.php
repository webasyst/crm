<?php

class crmContactOperationAssignTagsProcessController extends crmContactOperationProcessController
{
    protected $contact_ids;

    public function execute()
    {
        $contact_ids = $this->getContactIds();
        if (!$contact_ids) {
            return $this->done();
        }
        $tags = $this->getTags();
        if (!$this->isAssign() && !$tags) {
            return $this->done();
        }

        $offset = $this->getOffset();
        $offset += count($contact_ids);

        $this->process($tags, $contact_ids);

        if ($offset >= $this->getTotalCount()) {
            return $this->done();
        }

        $this->response(array(
            'offset' => $offset
        ));
    }

    protected function process($tags, $contact_ids)
    {
        if ($this->isAssign()) {
            $this->getTagModel()->assign($contact_ids, $tags);
        } else {
            $this->getTagModel()->add($contact_ids, $tags);
        }
    }

    protected function getTags()
    {
        $tags_str = wa()->getRequest()->post('tags', '', waRequest::TYPE_STRING_TRIM);
        if (!$tags_str) {
            return array();
        }
        $tags = array();
        foreach (explode(',', $tags_str) as $tag) {
            $tag = trim($tag);
            if (!$tag) {
                continue;
            }
            $tags[] = $tag;
        }
        return $tags;
    }

    protected function isAssign()
    {
        return !!$this->getRequest()->request('is_assign');
    }

    protected function dropUnallowed($contact_ids)
    {
        return $this->getCrmRights()->dropUnallowedContacts($contact_ids);
    }

    protected function getContactIds()
    {
        if ($this->contact_ids !== null) {
            return $this->contact_ids;
        }
        $this->contact_ids = parent::getContactIds();
        if (empty($this->contact_ids)) {
            return array();
        }
        return $this->dropUnallowed($this->contact_ids);
    }
}
