<?php

class crmAutocompleteContactController extends crmAutocompleteController
{
    const USERPIC = 32;

    public function execute()
    {
        $term = $this->getTerm();
        $with_email = (bool) waRequest::get('with_email', false);
        $with_phone = (bool) waRequest::get('with_phone', false);

        $key_names = ['id', 'name', 'userpic'];
        if (preg_match('#^\S*@\S*$#', $term)) {
            /** передан email */
            $key_names[] = 'email';
            $this->emailcomplete = true;
        } elseif (preg_match('#^\+?[-\s\d]*\(?[-\s\d]+\)?[-\s\d]+$#', $term)) {
            /** передан phone */
            $key_names[] = 'phone';
            $this->phonecomplete = true;
        }

        $sample = array_fill_keys($key_names, '');
        $contacts = $this->getContacts($term);
        $contacts = array_map(function ($c) use ($sample) {
            $c['name'] = htmlentities($c['name']);
            $c['userpic'] = rtrim(wa()->getConfig()->getHostUrl(), '/').(new crmContact($c['id']))->getPhoto(self::USERPIC);
            return array_intersect_key($c, $sample);
        }, $contacts);

        if (!empty($contacts)) {
            if (!$this->emailcomplete && $with_email) {
                $cem = new waContactEmailsModel();
                $emails = $cem->query("
                    SELECT contact_id, email FROM wa_contact_emails
                    WHERE contact_id IN (?)
                    GROUP BY contact_id, sort
                    HAVING sort = 0
                ", [array_column($contacts, 'id')])->fetchAll('contact_id');
                foreach ($contacts as &$_contact) {
                    if (isset($emails[$_contact['id']])) {
                        $_contact['email'] = ifset($emails, $_contact['id'], 'email', '');
                    }
                }
            }
            if (!$this->phonecomplete && $with_phone) {
                $cdm = new waContactDataModel();
                $phones = $cdm->query("
                    SELECT contact_id, value FROM wa_contact_data
                    WHERE contact_id IN (?) AND field = 'phone'
                    GROUP BY contact_id, sort
                    HAVING sort = 0            
                ", [array_column($contacts, 'id')])->fetchAll('contact_id');
                foreach ($contacts as &$_contact) {
                    if (isset($phones[$_contact['id']])) {
                        $_contact['phone'] = ifset($phones, $_contact['id'], 'value', '');
                    }
                }
            }
        }

        header('Content-type:application/json');

        die(json_encode($contacts));
    }
}
