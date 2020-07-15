<?php

class crmTwitterPluginContactExporter
{
    /**
     * @var crmTwitterPluginUser
     */
    protected $twitter_user;

    /**
     * @var array
     */
    protected $options;

    public function __construct($twitter_user, $options = array())
    {
        $this->twitter_user = $twitter_user;
        $this->options = $options;
    }

    /**
     * @return crmContact
     * @throws waException
     */
    public function exportContact()
    {
        $data = array();
        $this->prepareSimpleFields($data);
        $contact = new crmContact();
        $contact->save($data);
        if ($this->twitter_user->getPhotoUrl()) {
            $path = $this->downloadPhoto($this->twitter_user->getPhotoUrl());
            $contact->setPhoto($path);
            try {
                waFiles::delete($path);
            } catch (Exception $e) {
            }
        }
        return $contact;
    }

    protected function prepareSimpleFields(&$data)
    {
        $data = array(
            'name'                  => $this->twitter_user->getName(),
            'socialnetwork.twitter' => $this->twitter_user->getLogin() ? $this->twitter_user->getLogin() : null,
            'twitter_id'            => $this->twitter_user->getId(),
            'timezone'              => $this->twitter_user->getTimezone(),
            'create_app_id'         => 'crm',
            'create_contact_id'     => 0,
            'create_method'         => 'source/im/twitter',
            'crm_user_id'           => ifset($this->options['crm_user_id']),
        );
        foreach ($data as $key => $value) {
            if ($value === null) {
                unset($data[$key]);
            }
        }
    }

    protected function downloadPhoto($url)
    {
        // Get full size userpic:
        // Just remove from link '_normal'
        // @see https://developer.twitter.com/en/docs/accounts-and-users/user-profile-images-and-banners
        $url = str_replace('_normal', '', $url);

        $protocol = substr($url, 0, 5) === 'https' ? 'https' : 'http';
        $context_options = array(
            $protocol => array(
                'method' => 'GET'
            )
        );
        $context = stream_context_create($context_options);
        $input = fopen($url, 'rb', false, $context);

        $path = wa()->getTempPath('plugins/twitter/'.uniqid('userpic', true), 'crm');
        waFiles::create($path);
        $output = fopen($path, 'wb');

        stream_copy_to_stream($input, $output);

        fclose($input);
        fclose($output);

        return $path;
    }
}