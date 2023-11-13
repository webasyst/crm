<?php

class crmContactPhotoDialogAction extends webasystProfilePhotoAction
{
    public function execute()
    {
        $rights = new crmRights();
        $id = waRequest::request('id', null, 'int');
        if (!$id || !$rights->contactEditable($id)) {
            throw new waRightsException(_w('Access denied'));
        }

        parent::execute();
        $this->view->assign(array(
            'parent_template_path' => 'file:'.wa('webasyst')->getAppPath('templates/actions-legacy/profile/ProfilePhoto.html'),
        ));
    }
}
