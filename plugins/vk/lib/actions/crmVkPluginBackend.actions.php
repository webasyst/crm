<?php

class crmVkPluginBackendActions  extends waActions
{
    public function settingsAction()
    {
        $this->getView()->assign(array(
            'source_settings_url' => wa()->getAppUrl().'settings/sources/?type=im',
            'need_show_review_widget' => wa()->appExists('installer')
        ));

        $template = wa()->getAppPath('plugins/vk/templates/VkPluginSettings.html');
        $this->getView()->display($template);
    }
}
