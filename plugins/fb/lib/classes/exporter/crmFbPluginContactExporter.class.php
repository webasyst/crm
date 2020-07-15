<?php

class crmFbPluginContactExporter
{
    protected $fb_info;
    protected $options;

    public function __construct($fb_info, $options = array())
    {
        $this->fb_info = $fb_info;
        $this->options = $options;
    }

    /**
     * @return crmContact
     * @throws waException
     */
    public function export()
    {
        $data = array();
        $this->prepareSimpleFields($data);
        $contact = new crmContact();
        $contact->save($data);
        if (!empty($this->fb_info['profile_pic'])) {
            $this->setContactPhoto($contact);
        }
        return $contact;
    }

    protected function prepareSimpleFields(&$data)
    {
        $sex = null;
        if (!empty($data['gender'])) {
            $sex = ($data['gender'] == 'male') ? 'm' : 'f';
        }
        $data = array(
            'firstname'         => ifset($this->fb_info['first_name']),
            'lastname'          => ifset($this->fb_info['last_name']),
            'sex'               => $sex,
            'fb_source_id'      => $this->fb_info['id'],
            'create_app_id'     => 'crm',
            'create_contact_id' => 0,
            'create_method'     => 'source/im/fb',
            'crm_user_id'       => ifempty($this->options['crm_user_id']),
            'locale'            => ifset($this->fb_info['locale']),
        );
        foreach ($data as $key => $value) {
            if ($value === null) {
                unset($data[$key]);
            }
        }
    }

    /**
     * @param crmContact $contact
     * @throws waException
     */
    public function setContactPhoto(crmContact &$contact)
    {
        if ($contact->get('photo')) {
            return;
        }

        $url = $this->fb_info['profile_pic'];

        $protocol = substr($url, 0, 5) === 'https' ? 'https' : 'http';
        $context_options = array(
            $protocol => array(
                'method' => 'GET'
            )
        );
        $context = stream_context_create($context_options);
        $input = fopen($url, 'rb', false, $context);

        $path = wa()->getTempPath('plugins/fb/'.uniqid('userpic', true), 'crm');
        waFiles::create($path);
        $output = fopen($path, 'wb');

        stream_copy_to_stream($input, $output);

        fclose($input);
        fclose($output);

        $contact->setPhoto($path);
        try {
            waFiles::delete($path);
        } catch (Exception $e) {

        }
    }
}
