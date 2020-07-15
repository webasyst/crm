<?php
/** Contact photo editor: accepts a file and a crop area. */
class crmContactSavePhotoController extends webasystProfileSavePhotoController
{
    protected function getId()
    {
        $id = waRequest::post('id', null, 'int');

        $rights = new crmRights();
        if ($id && !$rights->contactEditable($id)) {
            return null;
        }

        return $id;
    }
}
