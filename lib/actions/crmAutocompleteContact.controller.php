<?php

class crmAutocompleteContactController extends crmAutocompleteController
{
    const USERPIC = 32;

    public function execute()
    {
        $term = $this->getTerm();

        $key_names = ['id', 'name', 'userpic', 'photo_url', 'label'];
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
            $c['userpic'] = $c['photo_url'];
            return array_intersect_key($c, $sample);
        }, $contacts);

        header('Content-type:application/json');

        die(json_encode($contacts));
    }
}
