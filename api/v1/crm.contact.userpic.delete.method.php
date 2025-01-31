<?php

class crmContactUserpicDeleteMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_DELETE;
    const USERPIC_EXT = 'jpg';

    public function execute()
    {
        $contact_id = (int) $this->get('id', true);

        if ($contact_id < 1 || !$this->getContactModel()->getById($contact_id)) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        } elseif (!$this->getCrmRights()->contactEditable($contact_id)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $dir = waContact::getPhotoDir($contact_id, true);
        $path = wa()->getDataPath($dir, true, 'contacts');

        if (file_exists($path)) {
            waFiles::delete($path);
        }
        $contact = new waContact($contact_id);
        $contact['photo'] = 0;
        $contact->save();

        $this->http_status_code = 204;
        $this->response = null;
    }
}