<?php

class crmWebasystBackend_pushHandler extends waEventHandler
{
    public function execute(&$params)
    {
        try {
            if (!wa()->getPush()->isEnabled()) {
                return false;
            }
        } catch (waException $e) {
            return false;
        }

        $current_app_info = ifempty($params, 'current_app_info', array());

        if (empty($current_app_info['id']) || $current_app_info['id'] != 'crm') {
            return false;
        }

        return true;
    }
}