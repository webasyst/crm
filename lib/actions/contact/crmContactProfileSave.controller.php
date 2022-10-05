<?php
/** save data that came from contact info profile tab */
class crmContactProfileSaveController extends webasystProfileSaveController
{
    protected function getContact()
    {
        $this->id = waRequest::post('id', null, 'int');
        if (!$this->id) {
            throw new waException('Not found', 404);
        }
        $contact = new waContact($this->id);

        if (!wa()->getUser()->isAdmin() && $contact['is_user'] > 0) {
            $this->errors[] = [
                'id' => 'not_enough_rights',
                'text' => _w('A user with limited access rights cannot edit other users’ profiles.'),
            ];
            return null;
        }

        $rights = new crmRights();
        if (!$rights->contactEditable($this->id)) {
            $this->errors[] = [
                'id' => 'cannot_be_edited',
                'text' => _w('This profile is not available for editing.'),
            ];
            return null;
        }

        return $contact;
    }

    protected function getData()
    {
        $data = json_decode(waRequest::post('data', '[]', 'string'), true);
        if (!$data || !is_array($data)) {
            return null;
        }

        // Bind employee to company contact if found
        if (isset($data['company'])) {

            $company = null;
            $data['company_contact_id'] = 0;
            $data['company'] = (string) $data['company'];

            // Existing company matches?
            if ($this->contact['company_contact_id']) {
                $company = new waContact($this->contact['company_contact_id']);
                if (!$company['exists'] || !$company['is_company'] || $company['name'] != $data['company']) {
                    $company = null;
                }
            }

            // Find company by name if existing one did not match
            if (!$company) {
                $contact_model = new waContactModel();
                $company = $contact_model->getByField(array(
                    'name' => $data['company'],
                    'is_company' => 1,
                ));
            }

            if ($company && $company['id'] != $this->id) {
                $data['company_contact_id'] = $company['id'];
            }
        } else {
            $contact = new waContact($this->id);
            if ($contact['is_company'] && isset($data['name'])) {
                $data['company'] = $data['name'];
            }
        }
        return $data;
    }
}
