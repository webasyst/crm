<?php

class crmRecentUnpinController extends crmJsonController
{
    public function execute()
    {
        $contact_id = waRequest::post('contact_id', null, waRequest::TYPE_INT);
        if (!$contact_id) {
            throw new waException('Invalid contact ID');
        }
        $rm = new crmRecentModel();
        $rm->deleteByField(array(
                'user_contact_id' => wa()->getUser()->getId(), 'contact_id' => $contact_id
        ));
    }
}
