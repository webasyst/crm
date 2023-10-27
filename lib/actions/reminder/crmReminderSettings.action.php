<?php

/**
 * Reminders settings
 */
class crmReminderSettingsAction extends crmBackendViewAction
{
    protected $is_dialog = true;

    public function __construct($params = null)
    {
        parent::__construct($params);
        if (isset($params['is_dialog'])) {
            $this->is_dialog = $params['is_dialog'];
        }
    }

    public function execute()
    {
        $groups = crmHelper::getAvailableGroups();

        $arrSetting = array(
            'reminder_setting'         => 'all',
            'reminder_recap'           => '0',
            'reminder_disable_done'    => 0,
            'reminder_disable_assign'  => 0,
            'reminder_daily'           => 'today',
            'reminder_pop_up_disabled' => 0,
            'reminder_pop_up_min'      => crmReminderModel::POP_UP_MIN
        );

        foreach ($arrSetting as $key => $value) {
            $arrSetting[$key] = wa()->getUser()->getSettings('crm', $key);
        }

        $this->view->assign(array(
            'groups'     => $groups,
            'settings'   => $arrSetting,
            'root_path'  => $this->getConfig()->getRootPath() . DIRECTORY_SEPARATOR,
            'is_dialog'  => $this->is_dialog,
            'pop_up_min' => crmReminderModel::POP_UP_MIN
        ));
    }

}
