<?php

class crmContactOperationExcludeFromSegmentAction extends crmContactOperationAction
{
    /**
     * @var crmSegmentModel
     */
    protected $sm;

    public function execute()
    {
        $segment = $this->getSegment();
        $this->checkRights($segment);

        $this->view->assign(array(
            'segment' => $segment,
            'count' => $this->getCheckedCount()
        ));
    }

    /**
     * @return null|array
     * @throws waException
     */
    protected function getSegment()
    {
        $id = (int) $this->getRequest()->request('id');
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
        if (!$this->getCrmRights()->canEditSegment($segment)) {
            $this->accessDenied();
        }
    }
}
