<?php

class crmSettingsTagsSaveController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        
        $data = waRequest::post();

        $data = array_filter($data, function ($value) {
            return wa_is_int($value);
        }, ARRAY_FILTER_USE_KEY);
        
        if (empty($data)) {
            return;
        }
        
        $tm = new crmTagModel();
        foreach ($data as $id => $color) {
            $tm->updateById($id, array('color' => $color));
        }
    }
}
