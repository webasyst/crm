<?php

class crmPop3EmailSourceSettingsPage extends crmEmailSourceSettingsPage
{
    protected function getConnectionSettingsBlock()
    {
        $template = 'templates/source/settings/EmailSourceSettingsPop3Block.html';
        return $this->renderTemplate($template, array(
            'source' => $this->source->getInfo()
        ));
    }
}
