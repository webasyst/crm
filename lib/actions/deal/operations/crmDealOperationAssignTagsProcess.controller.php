<?php

class crmDealOperationAssignTagsProcessController extends crmJsonController
{
    protected $deal_ids = array();

    public function execute()
    {
        $this->deal_ids = waRequest::post('deal_ids', array(), waRequest::TYPE_ARRAY_TRIM);

        if (!$this->deal_ids) {
            return $this->done();
        }
        $tags = $this->getTags();
        if (!$this->isAssign() && !$tags) {
            return $this->done();
        }

        $this->process($tags);

        return $this->done();
    }

    protected function process($tags)
    {
        $ids = array();
        foreach ($this->deal_ids as $id) {
            $ids[] = -$id;
        }
        if ($this->isAssign()) {
            $this->getTagModel()->assign($ids, $tags, false);
        } else {
            $this->getTagModel()->add($ids, $tags, false);
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

    protected function done($response = array())
    {
        $response['done'] = true;
        $this->response = $response;
    }
}
