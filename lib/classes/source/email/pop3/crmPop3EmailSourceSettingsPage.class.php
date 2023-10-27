<?php

class crmPop3EmailSourceSettingsPage extends crmEmailSourceSettingsPage
{
    protected function getConnectionSettingsBlock()
    {
        $source_path = wa('crm')->whichUI('crm') === '1.3' ? 'source-legacy' : 'source';
        $template = 'templates/'.$source_path.'/settings/EmailSourceSettingsPop3Block.html';
        return $this->renderTemplate($template, array(
            'source' => $this->source->getInfo()
        ));
    }
}
