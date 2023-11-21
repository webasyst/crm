<?php

class crmCallInitNewController extends crmJsonController
{

    public function execute()
    {
        $data = waRequest::post('call', array(), waRequest::TYPE_ARRAY_TRIM);

        $contact_id = (int) $data['contact_id'];
        if (!$contact_id) {
            throw new waException('Missing contact id', 404);
        }
        $contact = new crmContact($contact_id);
        if (empty($contact) || !$contact->exists()) {
            throw new waException(_w('Contact not found'), 404);
        }

        $client_number = (string) $data['to'];
        if (!$client_number) {
            throw new waException('Missing phone number', 404);
        }

        $deal_id = ifset($data['deal_id'], null);
        if ($deal_id) {
            $dm = new crmDealModel();
            $deal = $dm->getDeal($deal_id);
            if (!$deal) {
                throw new waException(_w('Deal not found'), 404);
            }
        }

        $pbx_number = (string) $data['from'];
        if (!$pbx_number) {
            throw new waException('Missing extension number', 404);
        }

        $pbx_plugin_id = $data['plugin_id'];
        if (!$pbx_plugin_id) {
            throw new waException('Missing pbx plugin id', 404);
        }
        $pbx_plugin = $this->getConfig()->getTelephonyPlugins($pbx_plugin_id);
        if (!$pbx_plugin) {
            throw new waException('Plugin '.$pbx_plugin_id.' is not a telephony plugin', 404);
        }
        if (!$pbx_plugin->isInitCallAllowed()) {
            throw new waException('The plugin does not know how to make outgoing calls via api', 404);
        }
        if (!$this->pbxNumberCheck($pbx_plugin_id, $pbx_number)) {
            throw new waException('You can not call using number '.$pbx_number, 404);
        }

        // create new outging call
        $call_data = array(
            'direction'            => 'OUT',
            'create_datetime'      => date('Y-m-d H:i:s'),
            'plugin_id'            => $pbx_plugin->getId(),
            'plugin_call_id'       => 'expected', // !!!
            'plugin_user_number'   => $pbx_number,
            'plugin_client_number' => $client_number,
            'deal_id'              => empty($deal) ? null : $deal_id,
            'client_contact_id'    => $contact['id'],
            'user_contact_id'      => wa()->getUser()->getId(),
        );
        $call_id = $this->getCallModel()->insert($call_data);
        $this->getLogModel()->log('call', empty($deal_id) ? $contact['id'] : -$deal_id, $call_id, null, null, $contact['id']);

        // Hmm.. Looks good. let's try
        $pbx_plugin->initCall($pbx_number, $client_number, $this->getCallModel()->getById($call_id));

        $this->response = array('call_id' => $call_id);
    }

    protected function pbxNumberCheck($plugin_id, $pbx_number)
    {
        $numbers = $this->getPbxUsersModel()->getByField(array(
            'plugin_id' => $plugin_id,
            'plugin_user_number' => $pbx_number,
            'contact_id' => wa()->getUser()->getId(),
        ));

        if (!$numbers) {
            return false;
        }

        return true;
    }
}