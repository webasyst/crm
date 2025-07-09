<?php

class crmContactSegmentEditAction extends crmBackendViewAction
{
    /**
     * @var crmSegmentModel
     */
    protected $sm;

    public function execute()
    {
        $this->view->assign(array(
            'segment' => $this->getSegment(),
            'icons' => crmSegmentModel::getIcons(wa()->whichUI()),
        ));
    }

    protected function getSegment()
    {
        $id = $this->getId();
        if ($id > 0) {
            $segment = $this->getSegmentModel()->getSegment($id);
            if ($segment['type'] === crmSegmentModel::TYPE_SEARCH) {
                $collection = new crmContactsCollection($segment['hash']);
                $collection->addWhere(0);
                $collection->count();
                $title = $collection->getTitle();
                $segment['search_info'] = array(
                    'hash' => str_replace('crmSearch/', '', $segment['hash']),
                    'title' => $title
                );
            }
        } else {
            $segment = $this->getSegmentModel()->getEmptyRowOfType($this->getType());
            $name = $this->getRequest()->request('name', '', waRequest::TYPE_STRING_TRIM);
            if (strlen($name) > 0) {
                $segment['name'] = $name;
            }
            if ($segment['type'] === crmSegmentModel::TYPE_SEARCH) {
                $segment['search_info'] = array(
                    'hash' => $this->getHash()
                );
            }
        }
        $segment['contact'] = new waContact($segment['contact_id']);
        return $segment;
    }

    protected function getType()
    {
        $type = $this->getRequest()->request('type');
        return $type === crmSegmentModel::TYPE_SEARCH ? crmSegmentModel::TYPE_SEARCH : crmSegmentModel::TYPE_CATEGORY;
    }

    protected function getHash()
    {
        return $this->getRequest()->request('hash', '', waRequest::TYPE_STRING_TRIM);
    }

    protected function getId()
    {
        return (int) $this->getRequest()->request('id');
    }
}
