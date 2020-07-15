<?php

class crmFbPluginImSourceSettingsPage extends crmImSourceSettingsPage
{
    /**
     * @var crmFbPluginImSource
     */
    protected $source;

    /**
     * @return string
     */
    protected function getSpecificSettingsBlock()
    {
        $template = wa()->getAppPath('plugins/fb/templates/source/settings/ImSourceSettingsFbBlock.html');
        $assign = array(
            'source'       => $this->source->getInfo(),
            'callback_url' => $this->getCallbackUrl(),
            'locale' => wa()->getLocale(),
        );
        return $this->renderTemplate($template, $assign);
    }

    protected function getCallbackUrl()
    {
        if ($this->source->getId() <= 0) {
            return false;
        }
        if (!wa()->getRouting()->getByApp('crm')) {
            return false;
        }
        return wa()->getRouteUrl('crm', array(
            'plugin' => 'fb',
            'module' => 'frontend',
            'action' => 'callback',
            'id'     => $this->source->getId(),
        ), true);
    }
}