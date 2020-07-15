<?php

abstract class crmVkPluginContactExporter
{
    /**
     * @return crmContact
     */
    public function export()
    {
        $contact = $this->doExport();
        $contact_id = $contact->getId();
        self::markContactAsJustExported($contact_id);
        return $contact;
    }

    protected static function markContactAsJustExported($contact_id)
    {
        $key = 'plugins/vk/contacts_just_exported';
        $cache = new waSerializeCache($key, 60);
        $just_exported = $cache->get();
        $just_exported = is_array($just_exported) ? $just_exported : array();
        $just_exported[$contact_id] = true;
        $cache->set($just_exported);
    }


    public static function isContactJustExported($contact_id)
    {
        $key = 'plugins/vk/contacts_just_exported';
        $cache = new waSerializeCache($key, 60);
        $just_exported = $cache->get();
        $just_exported = is_array($just_exported) ? $just_exported : array();
        return !!ifset($just_exported[$contact_id]);
    }


    /**
     * @return crmContact
     */
    abstract protected function doExport();

    /**
     * @param crmContact $contact
     * @param string $photo_url
     */
    protected function setPhoto($contact, $photo_url = '')
    {
        if (!$photo_url) {
            return;
        }
        $path = $this->downloadVkPhoto($photo_url);
        $contact->setPhoto($path);
        try {
            waFiles::delete($path);
        } catch (Exception $e) {

        }
    }

    protected function downloadVkPhoto($url)
    {
        $protocol = substr($url, 0, 5) === 'https' ? 'https' : 'http';
        $context_options = array(
            $protocol => array(
                'method' => 'GET'
            )
        );
        $context = stream_context_create($context_options);
        $input = fopen($url, 'rb', false, $context);

        $path = wa()->getTempPath('plugins/vk/' . uniqid('userpic', true), 'crm');
        waFiles::create($path);
        $output = fopen($path, 'wb');

        stream_copy_to_stream($input, $output);

        fclose($input);
        fclose($output);

        return $path;
    }

    /**
     * @param $name
     * @return array
     */
    protected function foundCountryByName($name)
    {
        $cm = new waCountryModel();

        $res = $cm->select('*')
            ->where("name LIKE 'l:n%'", array('n' => $name))
            ->fetchAssoc();
        if ($res) {
            return $res;
        }

        $res = $cm->select('*')
            ->where("name LIKE '%l:n%'", array('n' => $name))
            ->fetchAssoc();
        return $res;
    }
}
