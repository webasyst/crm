<?php

class crmContactSortSaveController extends crmJsonController
{
    /**
     * @var crmSegmentModel
     */
    protected $sm;

    public function execute()
    {
        $this->checkRights();

        $segments = $this->getSegments();
        if (!$segments) {
            return;
        }

        $sort = 0;
        $sm = $this->getSegmentModel();
        foreach ($segments as $id) {
            $sm->updateById($id, array('sort' => $sort++));
        }
    }

    protected function checkRights()
    {
        $is_admin = wa()->getUser()->isAdmin($this->getAppId());
        if ($this->isShared() && !$is_admin) {
            throw new waRightsException();
        }
    }

    protected function getSegments()
    {
        $is_shared = $this->isShared();
        $ids = $this->getRequest()->request('segments', array(), waRequest::TYPE_ARRAY_INT);
        $segments = $this->getSegmentModel()->getById($ids);
        $segment_ids = array();
        foreach ($ids as $id) {
            $segment = ifset($segments[$id]);
            if ($segment && boolval($segment['shared']) == $is_shared) {
                $segment_ids[] = $id;
            }
        }
        return $segment_ids;
    }

    protected function isShared()
    {
        return (bool) $this->getRequest()->request('shared');
    }
}
