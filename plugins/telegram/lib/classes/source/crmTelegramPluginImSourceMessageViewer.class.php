<?php

class crmTelegramPluginImSourceMessageViewer extends crmImSourceMessageViewer
{
    public function __construct(crmSource $source, $message, array $options = array())
    {
        parent::__construct($source, $message, $options);
        $this->message = crmTelegramPluginImSourceHelper::workupMessageForDialog($this->message);
    }

    protected function getTemplate()
    {
        return wa()->getAppPath('plugins/telegram/templates/source/message/TelegramImSourceMessageViewerDialog.html');
    }

    public function getAssigns()
    {
        return array(
            'from_html' => $this->getFromHtml(),
            'to_html'   => $this->getToHtml(),
        );
    }

    protected function getFromHtml()
    {
        $template = wa()->getAppPath('plugins/telegram/templates/source/message/FromBlock.html');
        return $this->renderTemplate($template, array(
            'message'     => $this->message,
            'plugin_icon' => $this->source->getIcon(),
        ));
    }

    protected function getToHtml()
    {
        $template = wa()->getAppPath('plugins/telegram/templates/source/message/ToBlock.html');
        return $this->renderTemplate($template, array(
            'message'     => $this->message,
            'app_icon'    => $this->getAppIcon(),
            'plugin_icon' => $this->source->getIcon(),
        ));
    }

    protected function getAppIcon()
    {
        $info = wa()->getAppInfo('crm');
        $sizes = array_keys($info['icon']);
        $size = min($sizes);
        return $info['icon'][$size];
    }
}
