<?php

class crmZadarmaPluginBackendActions extends waActions
{
    // called when user clicks a link to download call record
    public function getRecordLinkAction()
    {
        $plugin_call_id = waRequest::post('c', '', 'string');
        $plugin_record_id = waRequest::post('r', '', 'string');

        try {
            /** @var crmZadarmaPluginTelephony $plugin */
            $plugin = wa()->getConfig()->getTelephonyPlugins('zadarma');
            $record_url = $plugin->getRecordUrl($plugin_call_id, $plugin_record_id);
        } catch (Exception $e) {
            waLog::log("Error fetching URL of record {$plugin_record_id} for call {$plugin_call_id} from API: ".$e->getMessage().' ('.$e->getCode().')', 'crm/plugins/zadarma.log');
            $this->displayJson(null, array(
                _wp('Error fetching record URL from Zadarma API:').' '.$e->getMessage(),
            ));
            return;
        }

        if ($record_url) {
            $this->displayJson(array(
                'record_url' => $record_url,
            ));
        } else {
            waLog::log("Error fetching URL of record {$plugin_record_id} for call {$plugin_call_id} from API: record does not exist.", 'crm/plugins/zadarma.log');

            $call_model = new crmCallModel();
            $call_model->updateByField(array(
                'plugin_record_id' => $plugin_record_id,
            ), array(
                'plugin_record_id' => null,
            ));

            $this->displayJson(null, array(
                _wp('Record does not exist'),
            ));
        }
    }
}
