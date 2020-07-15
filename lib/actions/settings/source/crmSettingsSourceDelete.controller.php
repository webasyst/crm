<?php

class crmSettingsSourceDeleteController extends crmJsonController
{
    public function execute()
    {
        $this->accessDeniedForNotAdmin();
        $source = $this->getSource();
        $source->delete();
    }

    protected function getSource()
    {
        $id = (int)$this->getParameter('id');
        if ($id <= 0) {
            $this->notFound();
        }
        $source = crmSource::factory($id);
        if (!$source->exists()) {
            $this->notFound();
        }
        return $source;
    }
}
