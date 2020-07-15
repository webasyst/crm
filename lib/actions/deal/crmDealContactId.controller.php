<?php
/**
 * !!! TODO not used?
 * !!! TODO access rights check
 */
class crmDealContactIdController extends crmJsonController
{
    public function execute()
    {
        $id = waRequest::get('id', null, waRequest::TYPE_INT);

        $info = array();
        if ($id) {
            $c = new waContact($id);
            $info['company'] = $c['company'];
            $info['jobtitle'] = $c['jobtitle'];
            $info['phone'] = $c->get('phone', 'default');
            $info['email'] = $c->get('email', 'default');
        }
        $this->response = $info;
    }
}
