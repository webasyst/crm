<?php

class crmCallRecordUrlMethod extends crmApiAbstractMethod
{
    public function execute()
    {
        $plugin_id        = trim((string) $this->get('plugin', true));
        $plugin_call_id   = trim((string) $this->get('call_id', true));
        $plugin_record_id = trim((string) $this->get('record_id', true));

        if ($this->getUser()->getRights('crm', 'calls') == crmRightConfig::RIGHT_CALL_NONE) {
            throw new waAPIException('forbidden', 'Access denied', 403);
        }

        /** @var crmPluginTelephony $plugin */
        $plugin = wa('crm')->getConfig()->getTelephonyPlugins($plugin_id);
        if (!$plugin) {
            throw new waAPIException('invalid_param', 'Plugin not exists', 400);
        }

        try {
            $this->response = $plugin->getRecordUrl($plugin_call_id, $plugin_record_id);
        } catch (Exception $e) {
            throw new waAPIException('error_plugin_telephony', $e->getMessage(), 500);
        }
    }
}
