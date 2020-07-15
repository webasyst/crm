<?php

class crmSipuniPluginSettingsActions extends waActions
{
    protected function defaultAction()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $tplugin = wa('crm')->getConfig()->getTelephonyPlugins('sipuni');
        $pairs = $this->getPairs($tplugin, $tplugin->getNumbers(), $this->getTelephonyUsers());

        $plugin = array(
            'id'   => $tplugin->getId(),
            'name' => $tplugin->getName(),
            'icon' => $tplugin->getIcon(),
        );

        $this->display(array(
            'user'            => wa()->getSetting('user', '', array('crm', 'sipuni')),
            'integration_key' => wa()->getSetting('integration_key', '', array('crm', 'sipuni')),
            'pairs'           => $pairs,
            'plugin'          => $plugin,
            'callback_url'    => $this->getCallbackUrl(),
        ));
    }

    protected function getPairs($pbx_plugin, $numbers, $users)
    {
        // Which numbers are assigned to which users
        $pairs = array();
        $unknown_users = array();
        $numbers_no_user = $numbers;

        $pbx_users_model = new crmPbxUsersModel();
        $pbx_rows = $pbx_users_model->getByField('plugin_id', $pbx_plugin->getId(), true);

        foreach ($pbx_rows as $row) {
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
                    'number_label' => $row['plugin_user_number'],
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

        foreach ($numbers_no_user as $number => $number_label) {
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
        foreach ($pairs as $pair) {
            $data[$pair['number']] = $pair;
            unset($pair);
        }

        return $data;
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

    protected function getCallbackUrl()
    {
        $routing = wa()->getRouting()->getByApp('crm');
        if (!$routing) {
            return false;
        }
        return rtrim(wa()->getRouteUrl('crm', array(
            'plugin'     => 'telphin',
            'module'     => 'frontend',
            'action'     => 'callback',
            'event_type' => '',
            'auth_hash'  => '',
        ), true), '/');
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