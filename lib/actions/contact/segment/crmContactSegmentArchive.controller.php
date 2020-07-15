<?php

class crmContactSegmentArchiveController extends crmJsonController
{
    public function execute()
    {
        $this->response = 'ok';

        $id = waRequest::post('id', null, 'int');
        if (!$id) {
            return;
        }

        // Check rights
        $segment = $this->getSegmentModel()->getSegment($id);
        if (!$segment || !$this->getCrmRights()->canArchiveSegment($segment)) {
            $this->accessDenied();
        }

        // Update
        $value = waRequest::post('archive') ? 1 : 0;
        $this->getSegmentModel()->updateById($id, array(
            'archived' => $value,
        ));
    }
}
