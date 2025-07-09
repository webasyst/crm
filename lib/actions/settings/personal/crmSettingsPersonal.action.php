<?php

class crmSettingsPersonalAction extends crmSettingsViewAction
{
    public function execute()
    {
        $this->displayPersonalSettings();
    }

    protected function displayPersonalSettings()
    {
        $app_counter = wa()->getUser()->getSettings('crm', 'app_counter');
        if ($app_counter) {
            $app_counter = json_decode($app_counter, true);
        }
        if (!$app_counter || !is_array($app_counter)) {
            $app_counter = array(
                'new_messages' => 1,
                'new_deals' => 1,
                'overdue_reminders' => 1
            );
        }

        $push_settings = wa()->getUser()->getSettings('crm', 'push');
        if ($push_settings) {
            $push_settings = json_decode($push_settings, true);
        }

        $is_push_enabled = $is_onesignal_enabled = false;
        try {
            $push = wa()->getPush();
            $is_push_enabled = !empty($push) && $push->isEnabled();

            $onesignal = wa('crm')->getConfig()->getPushAdapter('onesignal');
            $is_onesignal_enabled = $onesignal->isEnabled();
        } catch (waException $e) {}

        $calls_has_access = wa()->getUser()->getRights('crm', 'calls') !== crmRightConfig::RIGHT_CALL_NONE;
        $this->view->assign([
            'personal_settings' => [
                'app_counter' => $app_counter,
                'push' => $push_settings,
                'reminder_settings_html' => crmHelper::renderViewAction(new crmReminderSettingsAction([
                    'is_dialog' => false
                ]))
            ],
            'is_push_enabled' => $is_push_enabled,
            'is_onesignal_enabled' => $is_onesignal_enabled,
            'sources' => $this->getActiveSources(),
            'pbx_numbers' => $calls_has_access ? $this->getPbxNumbers() : [],
            'calls_has_access' => $calls_has_access,
        ]);
    }

    protected function getActiveSources()
    {
        $active_sources = (new crmSourceModel)->getByField([
            'type' => [crmSourceModel::TYPE_EMAIL, crmSourceModel::TYPE_IM],
            'disabled' => 0
        ], 'id');

        $active_sources = array_map(function ($el) {
            $el['source'] = crmSource::factory($el);

            $el['icon_color'] = '#BB64FF';
            if ($el['type'] === crmSourceModel::TYPE_IM) {
                $el['icon_url'] = $el['source']->getIcon();
                $fa_icon = $el['source']->getFontAwesomeBrandIcon();
                if (ifset($fa_icon['icon_fab'])) {
                    $el['icon_fab'] = $fa_icon['icon_fab'];
                    $el['icon_color'] = $fa_icon['icon_color'];
                }
            } elseif ($el['type'] === crmSourceModel::TYPE_EMAIL) {
                $el['icon_fa'] = 'envelope';
            }

            return $el;
        }, $active_sources);

        return $active_sources;
    }

    protected function getPbxNumbers()
    {
        $pbx_plugins = wa('crm')->getConfig()->getTelephonyPlugins();
        $numbers = array();
        foreach($pbx_plugins as $p) {
            try {
                $numbers[$p->getId()] = $p->getNumbers();
            } catch (Exception $e) {
                
            }
        }

        $numbers_opts = array();
        foreach($numbers as $p_id => $nums) {
            foreach($nums as $n_id => $n_label) {
                $numbers_opts[$p_id.':'.$n_id] = $n_label.' ('.$pbx_plugins[$p_id]->getName().')';
            }
        }

        return $numbers_opts;
    }

}
