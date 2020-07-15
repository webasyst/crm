<?php

class crmContactSegmentSearchController extends crmJsonController
{
    public function execute()
    {
        $contact_id = waRequest::request('id', null, waRequest::TYPE_INT);

        $sql = "SELECT id,name,icon,shared,count FROM {$this->getSegmentModel()->getTableName()}
            WHERE type='search' AND archived=0 AND (shared=1 OR (shared=0 AND contact_id=".wa()->getUser()->getId().")) ORDER BY sort";
        $all_segments = $this->getSegmentModel()->query($sql)->fetchAll('id');

        $segments = array();
        foreach ($all_segments as $id => $s) {
            $collection = new crmContactsCollection('segment/'.$id, array(
                'update_count_ignore' => true
            ));
            $collection->addWhere('c.id='.$contact_id);
            if ($contacts = $collection->getContacts('id')) {
                $segments[] = $s;
            }
        }
        $this->response = array('segments' => $segments);
    }
}
