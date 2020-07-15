<?php

class crmContactOperationAssignTagsAction extends crmContactOperationAction
{
    protected $contact_ids;

    public function execute()
    {
        $contact_ids = $this->getContactIds();

        $this->view->assign(array(
            'popular_tags' => $this->getPopularTags(),
            'contact_tags' => count($contact_ids) == 1 ? $this->getContactTags($contact_ids[0]) : array(),
            'context' => $this->getContext(),
            'is_assign' => $this->isAssign()
        ));
    }

    protected function getContactTags($contact_id)
    {
        return $this->getTagModel()->getByContact($contact_id);
    }

    protected function getPopularTags()
    {
        return $this->getTagModel()->getPopularTags();
    }

    protected function dropUnallowed($contact_ids)
    {
        return $this->getCrmRights()->dropUnallowedContacts($contact_ids);
    }

    protected function getContactIds()
    {
        if ($this->contact_ids !== null) {
            return $this->contact_ids;
        }
        $this->contact_ids = parent::getContactIds();
        if (empty($this->contact_ids)) {
            $this->notFound();
        }
        $this->contact_ids = $this->dropUnallowed($this->contact_ids);
        if (empty($this->contact_ids)) {
            $this->accessDenied();
        }
        return $this->contact_ids;
    }

    protected function isAssign()
    {
        return !!$this->getRequest()->request('is_assign');
    }
}
