<?php

class crmUserMeMethod extends crmApiAbstractMethod
{
    public function execute()
    {
        $userpic_size = waRequest::get('userpic_size', '32', waRequest::TYPE_INT);
        $current_user = $this->getContactsMicrolist(
            [$this->getUser()->getId()],
            ['id', 'name', 'userpic'],
            $userpic_size
        );

        $this->response = reset($current_user);
    }
}
