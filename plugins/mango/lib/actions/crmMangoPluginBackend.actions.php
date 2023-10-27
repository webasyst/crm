<?php
class crmMangoPluginBackendActions extends waActions
{
    // called when user clicks a link to download call record
    public function getRecordLinkAction()
    {
        $plugin_call_id = waRequest::post('c', '', 'string');
        $plugin_record_id = waRequest::post('r', '', 'string');

        try {
            /** @var crmMangoPluginTelephony $plugin */
            $plugin = wa()->getConfig()->getTelephonyPlugins('mango');
            $record_url = $plugin->getRecordUrl($plugin_call_id, $plugin_record_id);
        } catch (Exception $e) {
            waLog::log("Error fetching URL of record {$plugin_record_id} for call {$plugin_call_id} from API: ".$e->getMessage().' ('.$e->getCode().')', 'crm/plugins/mango.log');
            $this->displayJson(null, array(
                _wp('Error fetching record URL from Mango API:').' '.$e->getMessage(),
            ));
            return;
        }

        if ($record_url) {
            $this->displayJson(array(
                'record_url' => $record_url,
            ));
        } else {
            waLog::log("Error fetching URL of record {$plugin_record_id} for call {$plugin_call_id} from API: record does not exist.", 'crm/plugins/mango.log');

            $this->displayJson(null, array(
                _wd('crm_mango', 'The record is not yet ready or does not exist.'),
            ));
        }
    }
}
