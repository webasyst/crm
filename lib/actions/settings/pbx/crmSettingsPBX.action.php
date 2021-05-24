<?php
class crmSettingsPBXAction extends crmSettingsViewAction
{
    public function execute()
    {
        // only allowed to admin
        if (!wa()->getUser()->isAdmin('crm')) {
            $this->accessDenied();
        }

        // Save from POST if data came
        //$this->saveFromPost();
        //$sort = waRequest::request('sort', 'user', 'string');

        // Plugins
        $pbx_plugins = wa('crm')->getConfig()->getTelephonyPlugins();

        // Users
        $users = $this->getTelephonyUsers();
        uasort($users, wa_lambda('$a,$b', 'return (int)strpos($a["name"], $b["name"]);'));

        // Numbers
        $numbers = array();
        $warnings = array();
        foreach($pbx_plugins as $p) {
            try {
                $numbers[$p->getId()] = $p->getNumbers();
            } catch (Exception $e) {
                $warnings[] = _w('Error in plugin').' '.$p->getId().': '.$e->getMessage();
            }
        }

        // Options for <select> of phone numbers
        $numbers_opts = array();
        foreach($numbers as $p_id => $nums) {
            foreach($nums as $n_id => $n_label) {
                $numbers_opts[$p_id.':'.$n_id] = $n_label.' ('.$pbx_plugins[$p_id]->getName().')';
            }
        }

        $this->view->assign(array(
            'users' => $users,
            'pbx_plugins' => $pbx_plugins,
            'numbers_opts' => $numbers_opts,
            'pairs' => $this->getPairs($pbx_plugins, $users, $numbers),
            'warnings' => $warnings,
            //'sort' => $sort,
        ));
    }

    protected function getTelephonyUsers()
    {
        $user_ids = array_keys(waUser::getUsers('crm'));
        if (!$user_ids) {
            throw new waException('no users');
        }

        $collection = new crmContactsCollection('users');
        $collection->addWhere('id IN ('.join(',', $user_ids).')');
        return $collection->getContacts(null, 0, count($user_ids));
    }

    protected function getPairs($pbx_plugins, $users, $numbers)
    {
        // Which numbers are assigned to which users
        $pairs = array();
        $unknown_users = array();
        $numbers_no_user = $numbers;

        $pbx_users_model = new crmPbxUsersModel();

        foreach($pbx_users_model->getAll() as $row) {
            if (empty($users[$row['contact_id']])) {
                $unknown_users[$row['contact_id']] = $row['contact_id'];
                continue;
            }

            //unset($users_no_number[$row['contact_id']]);
            unset($numbers_no_user[$row['plugin_id']][$row['plugin_user_number']]);

            $idx = $row['plugin_id'].'~'.$row['plugin_user_number'];
            if (empty($pairs[$idx])) {
                $plugin_name = ifset($pbx_plugins[$row['plugin_id']]) ? $pbx_plugins[$row['plugin_id']]->getName() : 'deleted plugin '.$row['plugin_id'];
                $plugin_icon = ifset($pbx_plugins[$row['plugin_id']]) ? $pbx_plugins[$row['plugin_id']]->getIcon() : null;
                $pairs[$idx] = array(
                    'plugin_id'    => $row['plugin_id'],
                    'plugin_name'  => $plugin_name,
                    'plugin_icon'  => $plugin_icon,
                    'number'       => $row['plugin_user_number'],
                    'number_label' => empty($numbers[$row['plugin_id']][$row['plugin_user_number']]) ? $this->formatClientNumber($row['plugin_user_number']) : $numbers[$row['plugin_id']][$row['plugin_user_number']],
                    'users'        => array($row['contact_id'] => $users[$row['contact_id']]),
                    'not_exist'    => empty($numbers[$row['plugin_id']][$row['plugin_user_number']]) ? true : false,
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

        foreach ($numbers_no_user as $plugin_id => $numbers) {
            foreach ($numbers as $number => $number_label) {
                $pairs[] = array(
                    'plugin_id'    => $plugin_id,
                    'plugin_name'  => $pbx_plugins[$plugin_id]->getName(),
                    'plugin_icon'  => $pbx_plugins[$plugin_id]->getIcon(),
                    'number'       => $number,
                    'number_label' => $number_label,
                    'users'        => null,
                    'not_exist'    => false,
                );
            }
        }

        // Add users with no number and numbers with no user,
        // then sort pairs.
        //if ($sort == 'user') {
        /*
        if ($users_no_number) {
            foreach ($users_no_number as $u) {
                $us[$u['id']] = $u;
            }
            $pairs[] = array(
                'plugin_id'    => null,
                'plugin_name'  => null,
                'number'       => null,
                'number_label' => null,
                'users'        => $us,
            );
        }
        */
        usort($pairs, array($this, 'sortPairsByNumber'));

        return $pairs;
    }

    protected function sortPairsByUser($a, $b)
    {
        if (empty($a['user'])) {
            return empty($b['user']) ? 0 : 1;
        }
        if (empty($b['user'])) {
            return -1;
        }
        return strcmp($a['user']['name'], $b['user']['name']);
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

    protected function sortPairsUserThenNumber($a, $b)
    {
        $result = $this->sortPairsByUser($a, $b);
        if ($result == 0) {
            return $this->sortPairsByNumber($a, $b);
        } else {
            return $result;
        }
    }

    protected function sortPairsNumberThenUser($a, $b)
    {
        $result = $this->sortPairsByNumber($a, $b);
        if ($result == 0) {
            return $this->sortPairsByUser($a, $b);
        } else {
            return $result;
        }
    }

    protected function saveFromPost()
    {
        $rows = array();
        $num_users = waRequest::post('num_users', null, 'array');
        $user_nums = waRequest::post('user_nums', null, 'array');
        if ($num_users) {
            foreach($num_users as $num => $user_ids) {
                list($plugin_id, $number) = self::parseNum($num);
                if (!$plugin_id) {
                    continue;
                }
                foreach(array_filter($user_ids, 'wa_is_int') as $user_id) {
                    $rows[] = array(
                        'plugin_id' => $plugin_id,
                        'plugin_user_number' => $number,
                        'contact_id' => $user_id,
                    );
                }
            }
        } else if ($user_nums) {
            foreach($user_nums as $user_id => $nums) {
                if (!wa_is_int($user_id)) {
                    continue;
                }
                foreach($nums as $num) {
                    list($plugin_id, $number) = self::parseNum($num);
                    if ($plugin_id) {
                        $rows[] = array(
                            'plugin_id' => $plugin_id,
                            'plugin_user_number' => $number,
                            'contact_id' => $user_id,
                        );
                    }
                }
            }
        } else {
            return;
        }

        $pbx_users_model = new crmPbxUsersModel();
        $pbx_users_model->truncate();
        $pbx_users_model->multipleInsert($rows);

        wa('crm')->event('pbx_numbers_assigned', $rows);
    }

    private static function parseNum($num)
    {
        $num = explode(':', $num, 2);
        if (!isset($num[1])) {
            return array(null, null);
        }
        return $num;
    }

    /**
     * Format plugin_client_number into human-readable representation.
     * @param $plugin_number string
     * @return string
     */
    protected function formatClientNumber($plugin_number)
    {
        class_exists('waContactPhoneField');
        $formatter = new waContactPhoneFormatter();
        $plugin_number = str_replace(str_split("+-() \n\t"), '', $plugin_number);
        return $formatter->format($plugin_number);
    }
}
