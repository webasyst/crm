<?php

class crmContactOperationExcludeFromSegmentProcessController extends crmContactOperationProcessController
{
    /**
     * @var crmSegmentModel
     */
    protected $sm;

    public function execute()
    {
        $segment = $this->getSegment();
        $this->checkRights($segment);

        $contact_ids = $this->getContactIds();
        if (!$contact_ids) {
            return $this->done(array('segment' => $segment));
        }

        $process_count = $this->getProcessCount();
        $process_count += count($contact_ids);

        $this->process($segment, $contact_ids);
        if ($process_count >= $this->getCheckedCount()) {
            return $this->done(array('segment' => $segment));
        }

        $this->response(array(
            'process_count' => $process_count,
            'offset' => 0
        ));
    }

    protected function process($segment, $contact_ids)
    {
        $this->getSegmentModel()->deleteFrom($segment['id'], $contact_ids);
    }

    /**
     * @return null|array
     * @throws waException
     */
    protected function getSegment()
    {
        $id = (int) $this->getRequest()->request('segment_id');
        if ($id <= 0) {
            $this->notFound();
        }
        $segment = $this->getSegmentModel()->getSegment($id);
        if (!$segment) {
            $this->notFound();
        }
        return $segment;
    }

    /**
     * @param array $segment
     * @throws waRightsException
     */
    protected function checkRights($segment)
    {
        if ($segment['type'] !== crmSegmentModel::TYPE_CATEGORY || !$this->getCrmRights()->canEditSegment($segment)) {
            $this->accessDenied();
        }
    }

    protected function getProcessCount()
    {
        return (int)$this->getRequest()->request('process_count');
    }
}
