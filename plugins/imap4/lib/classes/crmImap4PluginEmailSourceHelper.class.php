<?php

class crmImap4PluginEmailSourceHelper extends crmSourceHelper
{
    public function workupMessageInList($message)
    {
        $fa_icon = $this->source->getFontAwesomeIcon();
        if (ifset($fa_icon['icon_fa'])) {
            $message['icon_fa'] = $fa_icon['icon_fa'];
            $message['icon_color'] = $fa_icon['icon_color'];
        }
        $message['icon_url'] = $this->source->getIcon();
        $message['transport_name'] = $this->source->getName();
        return $message;
    }
}