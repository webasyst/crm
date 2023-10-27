<?php

class crmContactOperationAction extends crmBackendViewAction
{
    protected $limit = 100;

    /**
     * @var crmContactsCollection
     */
    protected $collection;

    /**
     * @var string
     */
    protected $collection_hash;

    /**
     * @var array
     */
    protected $contacts;

    /**
     * @var string
     */
    protected $contact_fields = 'id';

    protected function getCheckedCount()
    {
        return (int) $this->getRequest()->request('checked_count');
    }

    protected function getContactsCollection()
    {
        if ($this->collection !== null) {
            return $this->collection;
        }
        $this->collection = new crmContactsCollection($this->getCollectionHash());
        $this->collection->orderBy('id');
        return $this->collection;
    }

    /**
     * @return array[]int
     */
    protected function getContactIds()
    {
        return array_keys($this->getContacts());
    }

    protected function getContacts()
    {
        if ($this->contacts !== null) {
            return $this->contacts;
        }
        $offset = $this->getOffset();
        $this->contacts = $this->getContactsCollection()->getContacts($this->contact_fields, $offset, $this->limit);
        return $this->contacts;
    }

    protected function getCollectionHash()
    {
        if ($this->collection_hash !== null) {
            return $this->collection_hash;
        }
        if (!$this->isCheckedAll()) {
            $contact_ids = crmHelper::toIntArray($this->getRequest()->request('contact_ids'));
            $contact_ids = crmHelper::dropNotPositive($contact_ids);
            if (empty($contact_ids)) {
                $this->collection_hash = 'id/0';
            } else {
                $this->collection_hash = 'id/' . join(',', $contact_ids);
            }
        } else {
            $this->collection_hash = $this->getHash();
        }
        return $this->collection_hash;
    }

    /**
     * @return string
     */
    protected function getHash()
    {
        return trim((string)$this->getRequest()->request('hash'));
    }

    /**
     * @return bool
     */
    protected function isCheckedAll()
    {
        return $this->getRequest()->request('is_checked_all') ? true : false;
    }

    /**
     * @return int
     */
    protected function getOffset()
    {
        return (int)$this->getRequest()->request('offset');
    }

    protected function getPageCount()
    {
        return (int)$this->getRequest()->request('page_count');
    }

    protected function getContext()
    {
        $context = array(
            'total_count' => $this->getContactsCollection()->count(),
            'page_count' => $this->getPageCount(),
            'checked_count' => $this->getCheckedCount(),
            'is_checked_all' => (int) $this->isCheckedAll(),
            'hash' => $this->getHash(),
            'contact_ids' => null
        );
        if (!$context['is_checked_all']) {
            $context['contact_ids'] = $this->getContactIds();
        }
        return $context;
    }

    public static function checkSkipUpdateLastPage()
    {
        waRequest::setParam('skip_update_last_page', '1');
    }
}
