<?php

class crmContactSegmentAddContactsSaveController extends crmJsonController
{
    public function execute()
    {
        $segment = $this->getSegment();

        $this->checkRights($segment);

        $contact_ids = $this->getContactIds();
        $this->getSegmentModel()->addTo($segment['id'], $contact_ids);

        $this->response = array(
            'segment' => $this->getSegment()
        );
    }

    protected function getSegment()
    {
        $id = $this->getId();
        if ($id <= 0) {
            $this->notFound();
        }

        $segment = $this->getSegmentModel()->getSegment($id);
        if ($segment['type'] !== crmSegmentModel::TYPE_CATEGORY) {
            $this->notFound();
        }

        return $segment;
    }

    protected function getId()
    {
        return (int)$this->getRequest()->request('id');
    }

    protected function getContactIds()
    {
        $contact_id = $this->getRequest()->request('contact_id');
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        return $contact_ids;
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
}
