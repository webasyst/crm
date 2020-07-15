<?php

class crmSettingsSourceSaveController extends crmJsonController
{
    public function execute()
    {
        $this->accessDeniedForNotAdmin();
        wa()->getStorage()->close();

        $data = $this->getRequest()->post('source');
        $source = $this->getSource();
        $result = crmSourceSettingsPage::processSourceSubmit($source, $data);

        if ($result && !empty($result['errors'])) {
            $this->errors = $result['errors'];
            return;
        }

        if ($result && !empty($result['response'])) {
            $this->response = $result['response'];
            return;
        }
        $this->response = array(
            'source' => $source->getInfo()
        );
    }

    /**
     * @return crmSource
     */
    public function getSource()
    {
        $id = $this->getParameter('id');
        $source = crmSource::factory($id);
        if (!$source->exists() && wa_is_int($id)) {
            $this->notFound();
        }
        return $source;
    }
}
