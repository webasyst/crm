<?php

class crmGravitelPluginBackendActions extends waActions
{
    // called when user clicks a link to download call record
    public function getRecordLinkAction()
    {
        $call_id = waRequest::post('call', -1, waRequest::TYPE_INT);

        $cm = new crmCallModel();
        $call = $cm->getById($call_id);

        if ($call && isset($call['plugin_record_id']) && !empty($call['plugin_record_id'])) {
            /** @var crmGravitelPluginTelephony $plugin */
            $plugin = wa()->getConfig()->getTelephonyPlugins('gravitel');
            $this->displayJson([
                'record_url' => $plugin->getRecordUrl($call['plugin_call_id'], $call['plugin_record_id'])
            ]);
        } else {
            waLog::log("Error fetching URL of record for call {$call_id} from API: record does not exist.", 'crm/plugins/gravitel.log');
        }
    }
}
