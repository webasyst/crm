<?php

class crmNullSourceSettingsPage extends crmSourceSettingsPage
{
    protected function getTemplate()
    {
        $source_path = wa('crm')->whichUI('crm') === '1.3' ? 'source-legacy' : 'source';
        return 'templates/'.$source_path.'/settings/NullSourceSettings.html';
    }
}
