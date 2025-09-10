<?php

class crmSidebarSaveMenuStateController extends waJsonController
{

    public function execute()
    {
        $contact_settings_model = new waContactSettingsModel();

        $sidebar_menu_state = waRequest::post('sidebar_menu_state', null, waRequest::TYPE_STRING_TRIM);
        if ($sidebar_menu_state !== null) {
            $contact_settings_model->set(wa()->getUser()->getId(), 'crm', 'sidebar_menu_state', $sidebar_menu_state);
        }
    }
}
