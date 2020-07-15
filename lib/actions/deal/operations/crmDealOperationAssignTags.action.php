<?php

class crmDealOperationAssignTagsAction extends crmBackendViewAction
{
    protected $deal_ids;

    public function execute()
    {
        $this->deal_ids = waRequest::request('deal_ids', array(), waRequest::TYPE_ARRAY_TRIM);

        $this->view->assign(array(
            'popular_tags' => $this->getPopularTags(),
            'deal_tags'    => count($this->deal_ids) == 1 ? $this->getTagModel()->getByContact(-$this->deal_ids[0], false) : array(),
            'context'      => $this->getContext(),
            'is_assign'    => $this->isAssign()
        ));
    }

    protected function getPopularTags()
    {
        return $this->getTagModel()->getPopularTags();
    }

    protected function isAssign()
    {
        return !!$this->getRequest()->request('is_assign');
    }

    protected function getContext()
    {
        $context = array(
            'total_count' => count($this->deal_ids),
            'deal_ids'    => $this->deal_ids,
        );
        return $context;
    }

}
