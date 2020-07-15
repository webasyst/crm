<?php

class crmContactsCompositeCollection extends crmContactsCollection
{
    public $collections = array();

    public function __construct(array $collections)
    {
        $this->collections = array_values($collections);
    }

    public function getContacts($fields = "id", $offset = 0, $limit = 50)
    {
        $collections = $this->collections;

        // Skip collections at the begining of list as long as $offset exceeds their count
        while($collections && $offset > 0) {
            $c = array_shift($collections);
            $count = $c->count();
            if ($offset >= $count) {
                $offset -= $count;
            } else {
                array_unshift($collections, $c);
                break;
            }
        }

        // Fetch contacts from collection until $limit is reached
        $result = array();
        while($collections && $limit > 0) {
            $c = array_shift($collections);
            $contacts = $c->getContacts($fields, $offset, $limit);
            $result = array_merge($result, $contacts);
            $limit -= count($contacts);
            $offset = 0;
        }

        return $result;
    }

    public function count()
    {
        if ($this->count === null) {
            $this->count = 0;
            foreach($this->collections as $c) {
                $this->count += $c->count();
            }
        }

        return $this->count;
    }

    public function getTitle()
    {
        return $this->collections[0]->getTitle();
    }

    public function getInfo()
    {
        $info = $this->collections[0]->getInfo();
        if (isset($info['segment'])) {
            $info['segment']['count'] = $this->count();
            self::updateSegmentCounter($this->collections[0], $info['segment'], true);
        }
        return $info;
    }
}
