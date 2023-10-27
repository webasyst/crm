<?php

class crmImapPluginEmailSourceSettingsPage extends crmEmailSourceSettingsPage
{
    protected function getConnectionSettingsBlock()
    {
        $template = wa()->getAppPath('plugins/imap/templates/source/settings/EmailSourceSettingsImapBlock.html');
        return $this->renderTemplate($template, array(
            'source' => $this->source->getInfo()
        ));
    }
}
