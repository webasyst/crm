<?php

class crmCallRedirectDialogAction extends crmBackendViewAction
{
    public function execute()
    {
        $call_id = waRequest::get("id", 0, waRequest::TYPE_INT);

        $call_model = new crmCallModel();
        $call = $call_model->getById($call_id);

        if (!$call) {
            throw new waException(_w('Call not found'), 404);
        }
        $call['client_number'] = $this->formatNumber($call['plugin_client_number']);

        $candidates = array();
        $tplugin = wa('crm')->getConfig()->getTelephonyPlugins($call['plugin_id']);
        if ($tplugin) {
            $call['plugin_name'] = $tplugin->getName();
            $call['plugin_icon'] = $tplugin->getIcon();
            $call['redirect_allowed'] = $tplugin->isRedirectAllowed($call);

            // candidates (numbers) to redirect
            $candidates = $this->getPairs($tplugin, $tplugin->getRedirectCandidates($call), $this->getTelephonyUsers());
            if (isset($candidates[$call['plugin_user_number']])) {
                unset($candidates[$call['plugin_user_number']]);
            }
        }
        $contact = new crmContact($call['client_contact_id']);
        if (empty($contact) || !$contact->exists()) {
            $contact = array();
        }

        $this->view->assign(array(
            'call'       => $call,
            'contact'    => $contact,
            'candidates' => $candidates,
        ));
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

    protected function getTelephonyUsers()
    {
        $user_ids = array_keys(waUser::getUsers('crm'));
        if (!$user_ids) {
            throw new waException('no users');
        }

        $collection = new crmContactsCollection('users');
        $collection->addWhere('id IN ('.join(',', $user_ids).')');
        return $collection->getContacts('name,photo_url_16', 0, count($user_ids));
    }

    protected function getPairs($pbx_plugin, $numbers, $users)
    {
        // Which numbers are assigned to which users
        $pairs = array();
        $unknown_users = array();
        $numbers_no_user = $numbers;

        $pbx_users_model = new crmPbxUsersModel();

        foreach($pbx_users_model->getByField('plugin_id', $pbx_plugin->getId(), true) as $row) {
            if (empty($users[$row['contact_id']])) {
                $unknown_users[$row['contact_id']] = $row['contact_id'];
                continue;
            }

            //unset($users_no_number[$row['contact_id']]);
            unset($numbers_no_user[$row['plugin_user_number']]);

            $idx = $row['plugin_id'].'~'.$row['plugin_user_number'];
            if (empty($pairs[$idx])) {
                $pairs[$idx] = array(
                    'plugin_id'    => $row['plugin_id'],
                    'plugin_name'  => $pbx_plugin->getName(),
                    'plugin_icon'  => $pbx_plugin->getIcon(),
                    'number'       => $row['plugin_user_number'],
                    'number_label' => empty($numbers[$row['plugin_user_number']]) ? $this->formatNumber($row['plugin_user_number']) : $numbers[$row['plugin_user_number']],
                    'users'        => array($row['contact_id'] => $users[$row['contact_id']]),
                    'not_exist'    => empty($numbers[$row['plugin_user_number']]) ? true : false,
                );
            } else {
                $pairs[$idx]['users'][$row['contact_id']] = $users[$row['contact_id']];
            }
        }

        // Clean up table from obsolete records
        if ($unknown_users) {
            $pbx_users_model->deleteByField(array(
                'contact_id' => $unknown_users,
            ));
        }

        foreach($numbers_no_user as $number => $number_label) {
            $pairs[] = array(
                'plugin_id'    => $pbx_plugin->getId(),
                'plugin_name'  => $pbx_plugin->getName(),
                'plugin_icon'  => $pbx_plugin->getIcon(),
                'number'       => $number,
                'number_label' => $number_label,
                'users'        => null,
                'not_exist'    => false,
            );
        }

        usort($pairs, array($this, 'sortPairsByNumber'));

        $data = array();
        foreach ($pairs as $pair)
        {
            $data[$pair['number']] = $pair;
            unset($pair);
        }

        return $data;
    }

    protected function sortPairsByNumber($a, $b)
    {

        if (!isset($a['number_label'])) {
            return isset($b['number_label']) ? 1 : 0;
        }
        if (!isset($b['number_label'])) {
            return -1;
        }
        return strcmp($a['number_label'], $b['number_label']);
    }
}
