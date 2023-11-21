<?php

class crmContactResponsibleDialogAction extends waViewAction
{
    public function execute()
    {
        $contact_id = waRequest::request('contact_id', 0, 'int');
        if ($contact_id) {
            $contact = new waContact($contact_id);
        }

        $rights = new crmRights();
        if (!$rights->contactEditable($contact)) {
            $this->accessDenied();
        }

        if (empty($contact) || !$contact->exists()) {
            throw new waException(_w('Contact not found'), 404);
        }

        // Responsible currently assigned to this contact
        $responsible_data = NULL;
        $ask = NULL; // Текст вопроса, для комплита об удалении ответственного
        if ($contact['crm_user_id'] != NULL) { // Если ответственный есть
            try {   
                $responsible = new waContact($contact['crm_user_id']); // Получим его
                $responsible_data = array(
                    'id' => $responsible['id'],
                    'name' => $responsible['name'],
                    'photo_url' => $responsible->getPhoto(20)
                );
                $ask = sprintf(_w('Remove customer %s from responsibility of %s'), htmlspecialchars($contact['name']), htmlspecialchars($responsible['name']));
            } catch (waException $e) {
                $responsible_data = null;
            }
        }

        $this->view->assign(array(
            'contact' => $contact,
            'responsible' => $responsible_data,
            'ask' => $ask
        ));
    }
}