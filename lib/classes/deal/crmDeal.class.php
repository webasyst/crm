<?php

class crmDeal
{
    protected static $deal_model;
    protected static $funnel_model;
    protected static $app_settings_model;
    protected static $crm_rights;

    /**
     * Get counters for contacts
     * @param waContact|array $main_contact main contact (client) of deal
     * @param waContact[]|array[] $deal_contacts deal participants (clients)
     * @param bool $deal_has_order Has current deal related order. Impact to info texts about counters
     *
     * @return array $result
     *
     *   array $result['order_counters'] - optional. If shop app NOT exists there will not such structure in result
     *     string $result['order_counters'][<contact_id>] - formatted string about number of orders
     *     ..
     *   array $result['deal_counters']
     *     string $result['deal_counters'][<contact_id>] - formatted string about number of deals
     *     ..
     * @throws waException
     */
    public static function getDealPageContactCounters($main_contact, $deal_contacts, $deal_has_order)
    {
        $all_contact_ids = [];

        if ($main_contact) {
            $all_contact_ids[] = $main_contact['id'];
        }
        
        foreach ($deal_contacts as $deal_client) {
            $all_contact_ids[] = $deal_client['id'];
        }

        $all_contact_ids = array_unique($all_contact_ids);

        // IMPORTANT: invariant suppose all contacts are participants (crm_deal_participants.role_id == "CLIENT")
        // Otherwise deal counters will be not correct

        $result_counters = array();

        $shop_exists = wa()->appExists('shop');

        if ($shop_exists) {

            $order_counters = array_fill_keys($all_contact_ids, 0);

            if ($all_contact_ids) {
                wa('shop');

                $scm = new shopCustomerModel();
                $db_result = $scm->select('contact_id,number_of_orders')->where('contact_id IN (:ids)', array('ids' => $all_contact_ids))->query();
                foreach ($db_result as $item) {
                    $order_counters[$item['contact_id']] = $item['number_of_orders'];
                }

                foreach ($order_counters as $contact_id => &$counter) {
                    if ($counter == 0) {
                        $counter = _w("No orders");
                    } elseif ($counter == 1 && $deal_has_order && $contact_id == $main_contact['id']) {
                        // Heuristic, not 100% work, but for major simplification:
                        // if there is order assume main contact is order customer
                        $counter = _w("No other orders");
                    } else {
                        $counter = _w('%d order', '%d orders', $counter);
                    }

                }
                unset($counter);

            }

            $result_counters['order_counters'] = $order_counters;
        }

        $company_ids = array();
        $person_ids = array();

        if ($main_contact) {
            if ($main_contact['is_company']) {
                $company_ids[] = $main_contact['id'];
            } else {
                $person_ids[] = $main_contact['id'];
            }
        }

        foreach ($deal_contacts as $deal_client) {
            if ($deal_client['is_company']) {
                $company_ids[] = $deal_client['id'];
            } else {
                $person_ids[] = $deal_client['id'];
            }
        }


        $deal_counters = array_fill_keys($all_contact_ids, 0);
        foreach (self::getDealModel()->countByPersonClients($person_ids) as $contact_id => $counter) {
            $deal_counters[$contact_id] = $counter;
        }
        foreach (self::getDealModel()->countByCompanyClients($company_ids) as $contact_id => $counter) {
            $deal_counters[$contact_id] = $counter;
        }

        // format counters
        foreach ($deal_counters as &$counter) {
            if ($counter == 0) {
                $counter = _w("No deals");
            } elseif ($counter == 1) {
                // participant client is already count current deal, thus text "No other orders"
                $counter = _w("No other deals");
            } else {
                $counter = _w('%d deal', '%d deals', $counter);
            }
            // for other cases leave int counter as it
        }
        unset($counter);

        $result_counters['deal_counters'] = $deal_counters;

        return $result_counters;
    }

    public static function updateReminder($contact_id)
    {
        if (waConfig::get('is_template')) {
            return;
        }
        if ($contact_id && $contact_id < 0) {
            $deal_id = abs($contact_id);
            $dm = new crmDealModel();
            if (!$deal_id || !($deal = $dm->getById($deal_id))) {
                throw new waException(_w('Deal not found'));
            }
            $rm = new crmReminderModel();
            $reminder = $rm->select('*')->where(
                'contact_id = -'.(int)$deal_id.' AND complete_datetime IS NULL'
            )->order('due_date, ISNULL(due_datetime), due_datetime')->limit(1)->fetchAssoc();
            if ($reminder) {
                if (!empty($reminder['due_datetime'])) {
                    $reminder_datetime = $reminder['due_datetime'];
                } elseif (!empty($reminder['due_date'])) {
                    $reminder_datetime = $reminder['due_date'].' 23:59:59';
                } else {
                    $reminder_datetime = null;
                }
                $dm->updateById($deal_id, array('reminder_datetime' => $reminder_datetime));
            } else {
                $dm->updateById($deal_id, array('reminder_datetime' => null));
            }
        }
    }

    public static function getNewCount($max_id)
    {
        self::getDealModel();
        self::getFunnelModel();
        $funnels = self::$funnel_model->getAllFunnels();
        $where = "funnel_id IN('".join("','", self::$funnel_model->escape(array_keys($funnels)))."') AND status_id = 'OPEN' AND id >".(int)$max_id;

        $opened_deals = self::$deal_model->select('*')->where($where)->fetchAll('id');
        $count = 0;
        foreach ($opened_deals as $id => $d) {
            if (self::getCrmRights()->deal($d)) {
                $count++;
            }
        }
        return $count;
    }

    private static function getDealModel()
    {
        return self::$deal_model ? self::$deal_model : (self::$deal_model = new crmDealModel());
    }

    private static function getFunnelModel()
    {
        return self::$funnel_model ? self::$funnel_model : (self::$funnel_model = new crmFunnelModel());
    }

    private static function getCrmRights()
    {
        return self::$crm_rights ? self::$crm_rights : (self::$crm_rights = new crmRights());
    }

    public static function getRoleLabel($deal)
    {
        $dpm = new crmDealParticipantsModel();
        $participant = $dpm->getByField(array(
            'deal_id'    => $deal['id'],
            'contact_id' => $deal['contact_id'],
            'role_id'    => crmDealParticipantsModel::ROLE_CLIENT
        ));
        return ifset($participant['label']);
    }

    public static function close($deal_id, $action, $lost_id, $lost_text)
    {
        if (waConfig::get('is_template')) {
            return;
        }
        $lm = new crmLogModel();
        $dm = new crmDealModel();
        $now = date('Y-m-d H:i:s');

        if ($action == crmDealModel::STATUS_WON) {
            $action_id  = 'deal_won';
            $crm_log_id = $lm->log($action_id, $deal_id * -1, $deal_id);
            $dm->updateById($deal_id, [
                'status_id'         => crmDealModel::STATUS_WON,
                'closed_datetime'   => $now,
                'update_datetime'   => $now,
                'reminder_datetime' => null,
                'crm_log_id'        => $crm_log_id
            ]);
        } else {
            $action_id  = 'deal_lost';
            $crm_log_id = $lm->log($action_id, $deal_id * -1, $deal_id);
            $dlm = new crmDealLostModel();
            if ($lost_id && !$lost_text && ($lost = $dlm->getById($lost_id))) {
                $lost_text = $lost['name'];
            }
            $dm->updateById($deal_id, [
                'status_id'         => crmDealModel::STATUS_LOST,
                'lost_id'           => $lost_id,
                'lost_text'         => $lost_text,
                'closed_datetime'   => $now,
                'update_datetime'   => $now,
                'reminder_datetime' => null,
                'crm_log_id'        => $crm_log_id
            ]);
        }
        crmHelper::logAction($action_id, array('deal_id' => $deal_id));

        $rm = new crmReminderModel();
        $sql = "UPDATE {$rm->getTableName()} SET complete_datetime='$now' WHERE complete_datetime IS NULL AND create_datetime < '$now' AND contact_id=".($deal_id * -1);
        $rm->exec($sql);
    }

    /**
     * @param array $options
     * @return null|array
     */
    public static function cliOverdue($options = array())
    {
        $dsm = new crmDealStagesModel();
        $deal_stages = $dsm->getOverdue();

        /**
         * @event start_deal_stages_overdue_worker
         */
        wa('crm')->event('start_deal_stages_overdue_worker');

        foreach ($deal_stages as $d) {
            $dsm->updateById($d['deal_stage_id'], array('overdue_datetime' => date('Y-m-d H:i:s')));

            $crm_log_id = (new crmLogModel())->log(
                'deal_stage_overdue',
                -1 * ifset($d, 'id', 0)
            );
            $params = [
                'deal'       => $d,
                'crm_log_id' => $crm_log_id
            ];

            /**
             * @event deal_stage_overdue
             * @param array $params
             * @param array[]array $params['deal']
             * @return bool
             */
            wa('crm')->event('deal_stage_overdue', $params);
        }
        self::getSettingsModel()->set('crm', 'deal_stages_overdue_cli_end', date('Y-m-d H:i:s'));

        $count = count($deal_stages);
        return array(
            'total_count'     => $count,
            'processed_count' => $count,
            'count'           => $count,
            'done'            => $count,
        );
    }

    public static function getLastCliRunDateTime()
    {
        return self::getSettingsModel()->get('crm', 'deal_stages_overdue_cli_end');
    }

    public static function isCliOk()
    {
        return !!self::getLastCliRunDateTime();
    }

    protected static function getSettingsModel()
    {
        return !empty(self::$app_settings_model) ? self::$app_settings_model : (self::$app_settings_model = new waAppSettingsModel());
    }
}
