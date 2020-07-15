<?php

class crmContactSegmentSaveController extends crmJsonController
{
    /**
     * @var crmSegmentModel
     */
    protected $sm;

    public function execute()
    {
        $data = $this->getData();

        $errors = $this->validate($data);
        if ($errors) {
            $this->errors = $errors;
            return;
        }

        $id = $this->getId();
        if ($id <= 0) {
            $segment = $this->add($data);
        } else {
            $segment = $this->update($id, $data);
        }

        if (!$segment) {
            return;
        }

        $this->response = array(
            'segment' => $segment
        );
    }

    protected function validate($data)
    {
        $errors = array();
        if (strlen($data['name']) <= 0) {
            $errors['name'] = _w('Name is required');
        }
        return $errors;
    }

    /**
     * @param array $data
     * @return null|array
     */
    protected function add($data)
    {
        $id = (int) $this->getSegmentModel()->add($data);
        if ($id <= 0) {
            return null;
        }
        return $this->getSegmentModel()->getSegment($id);
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
        if ($this->isRename()) {
            $data = array('name' => $data['name']);
        }
        $this->getSegmentModel()->update($id, $data);
        return $this->getSegmentModel()->getSegment($id);
    }

    protected function getData()
    {
        $data = array(
            'type' => $this->getType(),
            'hash' => $this->getHash(),
            'name' => $this->getName(),
            'icon' => $this->getIcon(),
            'shared' => $this->getShared(),
            'contacts' => array()
        );
        if ($this->getId() > 0) {
            unset($data['type'], $data['contacts']);
        }
        return $data;
    }

    protected function isRename()
    {
        return (bool)$this->getRequest()->request('is_rename');
    }

    protected function getType()
    {
        $type = $this->getRequest()->request('type');
        return $type === crmSegmentModel::TYPE_SEARCH ? crmSegmentModel::TYPE_SEARCH : crmSegmentModel::TYPE_CATEGORY;
    }

    protected function getName()
    {
        return trim((string) $this->getRequest()->request('name'));
    }

    protected function getIcon()
    {
        $icon = trim((string)$this->getRequest()->request('icon'));
        if (strlen($icon) <= 0) {
            $icon = null;
        }
        return $icon;
    }

    protected function getShared()
    {
        $shared = (int)$this->getRequest()->request('shared');
        return $shared === 0 ? 0 : 1;
    }

    protected function getHash()
    {
        $hash = trim((string)$this->getRequest()->request('hash'));
        return "crmSearch/{$hash}";
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
