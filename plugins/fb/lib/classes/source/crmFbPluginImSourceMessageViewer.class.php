<?php

class crmFbPluginImSourceMessageViewer extends crmImSourceMessageViewer
{
    public function __construct(crmSource $source, $message, array $options = array())
    {
        parent::__construct($source, $message, $options);
        $this->message = crmFbPluginImSourceHelper::workupMessageForDialog($this->message);
    }

    protected function getTemplate()
    {
        return wa()->getAppPath('plugins/fb/templates/source/message/ViewerDialog.html');
    }

    public function getAssigns()
    {
        return array(
            'from_contact' => new crmContact($this->message['creator_contact_id']),
            'app_icon_url' => $this->getAppIcon(),
        );
    }

    protected function getAppIcon()
    {
        $info = wa()->getAppInfo('crm');
        $sizes = array_keys($info['icon']);
        $size = min($sizes);
        return $info['icon'][$size];
    }
}
