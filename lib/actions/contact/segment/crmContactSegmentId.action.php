<?php

class crmContactSegmentIdAction extends crmContactsAction
{
    protected $id;

    protected function afterExecute()
    {
        $info = $this->getCollection()->getInfo();
        $segment = ifset($info['segment']);

        if (!$segment) {
            $this->notFound();
        }
        $this->view->assign(array(
            'segment' => $segment,
            'title' => strlen($info['segment']['name']) > 0 ? $info['segment']['name'] : sprintf(_w('Segment #%s'), $info['segment']['id']),
            'can_archive' => $this->getCrmRights()->canArchiveSegment($segment),
            'can_edit' => $this->canEdit($segment),
        ));
    }

    protected function getHash()
    {
        $segment_id = $this->getSegmentId();
        return "segment/{$segment_id}";
    }

    protected function canEdit($segment)
    {
        return $this->getCrmRights()->canEditSegment($segment);
    }

    protected function getSegmentId()
    {
        if ($this->id !== null) {
            return $this->id;
        }
        $this->id = (int) $this->getParameter('id');
        if ($this->id <= 0) {
            $this->notFound();
        }
        return $this->id;
    }
}
