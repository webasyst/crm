<?php

class crmCallInitContactDialogAction extends crmBackendViewAction
{
    protected $pbx_numbers;
    protected $pbx_plugins;
    protected $call_ready = 'not_ready';

    public function execute()
    {
        $contact_id = waRequest::post('contact_id', 0, waRequest::TYPE_INT);
        if (!$contact_id) {
            throw new waException('Missing contact id', 404);
        }
        $contact = new crmContact($contact_id);
        if (empty($contact) || !$contact->exists()) {
            throw new waException('Contact not found', 404);
        }

        $client_number = waRequest::post('phone', 0, waRequest::TYPE_STRING_TRIM);
        if (!$client_number) {
            throw new waException('Missing phone number', 404);
        }
        // 8 and 7 at the beginning of the line, replace with +7
        $client_number = preg_replace('~^(8|7)~', '+7', $client_number);

        $deal_id = waRequest::post('deal_id', null, waRequest::TYPE_INT);
        if ($deal_id) {
            $dm = new crmDealModel();
            $deal = $dm->getDeal($deal_id);
            if (!$deal) {
                throw new waException('Deal not found', 404);
            }
        }

        // Pbx numbers by user
        $user_pbx_numbers = $this->getPbxNumbers();

        if (empty($user_pbx_numbers)) {
            throw new waException('You are not assigned any telephony plugin number', 404);
        }

        // If only one assigned number - immediately initialize the call
        if (count($user_pbx_numbers) == 1) {
            $this->call_ready = 'ready';
        }

        $this->view->assign(array(
            'contact'              => $contact,
            'client_number'        => $client_number,
            'format_client_number' => $this->formatNumber($client_number),
            'call_ready'           => $this->call_ready,
            'pbx_numbers'          => $this->getPbxNumbers(),
            'deal_id'              => $deal_id,
        ));
    }

    /**
     * @return crmPluginTelephony[]
     * @throws waException
     */
    protected function getPbxPlugins()
    {
        if ($this->pbx_plugins) {
            return $this->pbx_plugins;
        }

        // Telephony plugins
        $pbx_plugins = $this->getConfig()->getTelephonyPlugins();
        if (!$pbx_plugins) {
            throw new waException('Telephony plugins not found', 404);
        }
        $this->pbx_plugins = $pbx_plugins;

        return $this->pbx_plugins;
    }

    protected function getPbxNumbers()
    {
        if ($this->pbx_numbers) {
            return $this->pbx_numbers;
        }

        // Telephony plugins
        $pbx_plugins = $this->getPbxPlugins();

        // All pbx numbers by user
        $pbx_user_model = new crmPbxUsersModel();
        $pbx_numbers = $pbx_user_model->getByContact(wa()->getUser()->getId());

        foreach ($pbx_numbers as $index => &$number) {
            $plugin = ifempty($pbx_plugins, $number['plugin_id'], null);
            if (!$plugin) {
                unset($pbx_numbers[$index]);
                continue;
            }

            /**
             * @var $plugin crmPluginTelephony
             */

            $number += array(
                'plugin_name' => $plugin->getName(),
                'plugin_icon' => $plugin->getIcon(),
            );

            // Remove the numbers whose plugins do not support outgoing calls via api
            if (!$plugin->isInitCallAllowed()) {
                array_splice($pbx_numbers, array_search($number, $pbx_numbers), 1);
            }
        }
        unset($number);

        $this->pbx_numbers = $pbx_numbers;

        return $this->pbx_numbers;
    }

    /**
     * Format telephony number into human-readable representation.
     * @param $number string
     * @return string
     */
    public function formatNumber($number)
    {
        class_exists('waContactPhoneField');
        $formatter = new waContactPhoneFormatter();
        $number = str_replace(str_split("+-() \n\t"), '', $number);
        return $formatter->format($number);
    }
}