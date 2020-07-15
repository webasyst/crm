<?php

class crmContactSegmentAddContactsAction extends crmBackendViewAction
{
    public function execute()
    {
        $this->view->assign(array(
            'segment' => $this->getSegment()
        ));
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
}
