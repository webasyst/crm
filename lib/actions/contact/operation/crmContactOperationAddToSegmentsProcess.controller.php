<?php

class crmContactOperationAddToSegmentsProcessController extends crmContactOperationProcessController
{
    /**
     * @var crmSegmentModel
     */
    protected $sm;

    public function execute()
    {
        $segment_ids = $this->getSegmentIds();
        if (!$segment_ids && !$this->isAssign()) {
            return $this->done();
        }

        $contact_ids = $this->getContactIds();
        if (!$contact_ids) {
            return $this->done($segment_ids);
        }

        $offset = $this->getOffset();
        $offset += count($contact_ids);

        $this->process($segment_ids, $contact_ids);

        $this->response(array(
            'offset' => $offset
        ));
    }

    protected function done($segment_ids = array())
    {
        $segments = $segment_ids ? $this->getSegmentModel()->getSegments($segment_ids) : array();
        parent::done(array(
            'segments' => $segments
        ));
    }

    protected function process($segment_ids, $contact_ids)
    {
        if ($this->isAssign()) {
            $this->getSegmentModel()->assignWith($segment_ids, $contact_ids);
        } else {
            $this->getSegmentModel()->addTo($segment_ids, $contact_ids);
        }
    }

    protected function isAssign()
    {
        return $this->getRequest()->request('is_assign');
    }

    protected function getSegmentIds()
    {
        $segment_ids = crmHelper::toIntArray($this->getRequest()->request('segment_ids'));
        return crmHelper::dropNotPositive($segment_ids);
    }

}
