<?php

class crmContactSegmentDialogSaveController extends crmJsonController
{
    /**
     * @var crmSegmentModel
     */
    protected $sm;

    public function execute()
    {
        $data = $this->getData();

        $id = $this->getId();
        if ($id <= 0) {
            throw new waException('Segment not found');
        }
        $segment = $this->update($id, $data);

        $this->response = array(
            'segment' => $segment
        );
    }

    /**
     * @param int $id
     * @param array $data
     * @return null|array
     * @throws waRightsException
     * @throws waException
     */
    protected function update($id, $data)
    {
        $segment = $this->getSegmentModel()->getSegment($id);
        if (!$segment || $segment['id'] <= 0) {
            $this->notFound();
        }
        $this->checkRights($segment);
        $this->getSegmentModel()->update($id, $data);
        return $this->getSegmentModel()->getSegment($id);
    }

    protected function getData()
    {
        return array(
            'contacts' => array()
        );
    }

    /**
     * @param $segment
     * @throws waRightsException
     */
    protected function checkRights($segment)
    {
        if (!$this->getCrmRights()->canEditSegment($segment)) {
            $this->accessDenied();
        }
    }

    protected function getId()
    {
        return (int) $this->getRequest()->request('id');
    }
}
