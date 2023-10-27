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
            'alias' => wa()->getAppUrl('crm', true).'settings/message-sources/'.strtolower($source->getType()).'/',
            'settings_html' => crmSourceSettingsPage::renderSource($source)
        ));
    }
}
