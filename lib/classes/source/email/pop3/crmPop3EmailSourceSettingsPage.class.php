<?php

class crmPop3EmailSourceSettingsPage extends crmEmailSourceSettingsPage
{
    protected function getMailProviderConnectionKind()
    {
        return 'pop3';
    }

    protected function getConnectionSettingsBlock()
    {
        $source_path = wa('crm')->whichUI('crm') === '1.3' ? 'source-legacy' : 'source';
        $template = 'templates/'.$source_path.'/settings/EmailSourceSettingsPop3Block.html';
        return $this->renderTemplate($template, array(
            'source' => $this->source->getInfo(),
            'mail_providers' => crmEmailSource::getMailProvidersForUi(),
            'mail_presets' => crmEmailSource::getMailProviderPresets('pop3'),
            'mail_provider_domain_map' => crmEmailSource::getMailProviderDomainMap(),
            'mail_provider_default' => crmEmailSource::DEFAULT_MAIL_PROVIDER_ID,
            'login_differs_from_email' => crmEmailSourceSettingsPage::emailSourceLoginDiffersFromEmail($this->source->getInfo()),
        ));
    }
}
