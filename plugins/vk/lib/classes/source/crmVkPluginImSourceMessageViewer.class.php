<?php

class crmVkPluginImSourceMessageViewer extends crmImSourceMessageViewer
{
    public function __construct(crmSource $source, $message, array $options = array())
    {
        /**
         * @var crmVkPluginImSource $source
         */
        parent::__construct($source, $message, $options);
        crmVkPluginImSourceHelper::markMessageAsRead($this->message, array(
            'access_token' => $source->getAccessToken()
        ));
        $this->message = crmVkPluginImSourceHelper::workupMessageForDialog($this->message);
    }

    public function getAssigns()
    {
        return array(
            'from_html' => $this->getFromHtml(),
            'to_html'   => $this->getToHtml()
        );
    }

    protected function getFromHtml()
    {
        if ($this->message['direction'] == crmMessageModel::DIRECTION_IN) {
            $template_name = "InMessageFromBlock.html";
        } else {
            $template_name = "OutMessageFromBlock.html";
        }
        $template = wa()->getAppPath("plugins/vk/templates/source/message/{$template_name}");
        return $this->renderTemplate($template, array(
            'message'         => $this->message,
            'app_icon_url'    => $this->getAppIcon(),
            'source_icon_url' => $this->source->getIcon(),
        ));
    }

    protected function getToHtml()
    {
        if ($this->message['direction'] == crmMessageModel::DIRECTION_IN) {
            $template_name = "InMessageToBlock.html";
        } else {
            $template_name = "OutMessageToBlock.html";
        }
        $template = wa()->getAppPath("plugins/vk/templates/source/message/{$template_name}");
        return $this->renderTemplate($template, array(
            'message'         => $this->message,
            'app_icon_url'    => $this->getAppIcon(),
            'source_icon_url' => $this->source->getIcon(),
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
