<?php

class crmSettingsHelpAction extends crmViewAction
{
    public function execute()
    {
    }

    public function display($clear_assign = true)
    {
        $cheat_sheet = new webasystBackendCheatSheetActions();
        $cheat_sheet->setTemplate('templates/actions/Help.html');
        return $cheat_sheet->cheatSheetAction();

    }
}
