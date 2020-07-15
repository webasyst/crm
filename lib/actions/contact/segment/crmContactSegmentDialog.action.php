<?php

class crmContactSegmentDialogAction extends crmContactsAction
{
    protected $id;

    protected function afterExecute()
    {
        $this->id = waRequest::request('id', null, waRequest::TYPE_INT);

        $segment = $this->getSegmentModel()->getSegment($this->id);
        if ($segment['type'] === crmSegmentModel::TYPE_SEARCH) {
            throw new waRightsException();
        }
        if (!$this->getCrmRights()->canEditSegment($segment)) {
            throw new waRightsException();
        }
        $segment['contact'] = new waContact($segment['contact_id']);

        $this->view->assign(array(
            'segment' => $segment,
            'title' => $segment['name'] ? $segment['name'] : sprintf(_w('Segment #%s'), $segment['id']),
        ));
    }
}
