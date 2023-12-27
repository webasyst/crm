<?php

class crmSettingsHelpAction extends crmViewAction
{
    public function execute()
    {
    }

    public function display($clear_assign = true)
    {
        $cheat_sheet = new webasystBackendCheatSheetActions();
        $template = wa('crm')->getConfig()->getAppPath('templates/actions/Help.html');
        $cheat_sheet->setTemplate($template);
        return $cheat_sheet->cheatSheetAction();
    }
}
