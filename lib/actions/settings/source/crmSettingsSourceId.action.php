<?php

class crmSettingsSourceIdAction extends crmSettingsViewAction
{
    public function execute()
    {
        $this->accessDeniedForNotAdmin();

        $id = $this->getParameter('id');
        /**
         * @var crmSource $source
         */
        $source = crmSource::factory($id);
        if (!$source->exists() && wa_is_int($id)) {
            $this->notFound();
        }
        $this->view->assign(array(
            'settings_html' => crmSourceSettingsPage::renderSource($source)
        ));
    }
}
