<?php

abstract class crmImSourceSettingsPage extends crmSourceSettingsPage
{
    /**
     * @var crmImSource
     */
    protected $source;

    /**
     * @override
     * @return string
     */
    protected function getSpecificSettingsBlock()
    {
        return '';
    }

    protected function getAssigns()
    {
        return [
            'blocks' => $this->getBlocks(),
            'default_verify_request' => _w('Please verify your client profile.'),
            'default_verify_request_button'=> _w('Verify client profile'),
            'default_verify_done_response' => _w('Your client profile has been verified.'),
            'default_verify_been_response' => _w('Your client profile was already verified.'),
            'verification_key' => $this->source->getParam('verification_key') ?: waUtils::getRandomHexString(16),
        ];
    }

    protected function getTemplate()
    {
        $source_path = wa('crm')->whichUI('crm') === '1.3' ? 'source-legacy' : 'source';
        return 'templates/'.$source_path.'/settings/ImSourceSettings.html';
    }

    /**
     * @override
     * @param $data
     * @return array
     */
    public function processSubmit($data)
    {
        $result = array(
            'status' => 'ok',
            'errors' => array(),
            'response' => array()
        );

        $data = $this->workupSubmitData($data);

        $errors = $this->validateSubmit($data);
        if ($errors) {
            $result['status'] = 'failed';
            $result['errors'] = $errors;
            return $result;
        }

        $this->source->setConnectionParams($data['params']);

        $errors = $this->source->testConnection();
        if ($errors) {
            $result['status'] = 'failed';
            $result['errors'] = $errors;
            return $result;
        }

        $this->source->save($data);

        $result['response'] = array(
            'source' => $this->source->getInfo()
        );

        return $result;
    }

    protected function workupSubmitData($data)
    {
        foreach ($data as $key => $value) {
            if (empty($value)) {
                unset($data[$key]);
            }
        }

        $data['params'] = (array)ifset($data['params']);

        if (empty($data['params']['create_deal'])) {
            $data['funnel_id'] = null;
            $data['stage_id'] = null;
        }
        if (empty($data['params']['segments'])) {
            $data['params']['segments'] = null;
        }
        if (empty($data['responsible_contact_id'])) {
            $data['responsible_contact_id'] = null;
        }

        return $data;
    }

    protected function getBlocks()
    {
        foreach (array(
             new crmSourceSettingsWithContactViewBlock('with_contact', $this->source),
             new crmSourceSettingsResponsibleViewBlock('responsible', $this->source),
             new crmSourceSettingsCreateDealViewBlock('create_deal', $this->source)
         ) as $block) {
            $blocks[$block->getId()] = $block->render(array(
                'namespace' => 'source'
            ));
        }
        $blocks['specific_settings_block'] = $this->getSpecificSettingsBlock();
        return $blocks;
    }

    /**
     * Extend if needed
     * @extend
     * @param $data
     * @return array
     */
    protected function validateSubmit($data)
    {
        $errors = array();
        $name = (string)ifset($data['name']);
        if (strlen($name) <= 0) {
            $errors['name'] = _w('Name is required');
        }
        return $errors;
    }
}
