<?php

class crmSettingsSourceDisableController extends crmJsonController
{
    public function execute()
    {
        $this->accessDeniedForNotAdmin();

        $id = (int)$this->getRequest()->post('id');
        $source = crmSource::factory($id);

        if ($this->getParameter('disabled')) {
            $source->saveAsDisabled();
        } else {
            $source->saveAsEnabled();
        }

        $this->response = array(
            'disabled' => $source->isDisabled()
        );
    }
}
