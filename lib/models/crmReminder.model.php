<?php

class crmReminderModel extends crmModel
{
    const POP_UP_MIN = 10;  // minutes;

    protected $table = 'crm_reminder';

    protected $link_contact_field = array('creator_contact_id', 'contact_id', 'user_contact_id');

    protected $unset_contact_links_behavior = array(
        'contact_id' => 'delete'
    );

    /**
     * @param int|array[] int $contact_id
     */
    public function deleteByContact($contact_id)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (!$contact_ids) {
            return;
        }
        $this->deleteByField('contact_id', $contact_ids);
    }

    /**
     * Get a list of users for whom the daily mailing of reminders is included and
     * who have not received the newsletter for more than 23 hours
     * @return array `contact-id` and `value`
     */
    public function getUsersForSendReminders()
    {
        $date = date('Y-m-d H:i:s', strtotime('-23 hours'));

        $sql = "SELECT
                  f.contact_id,
                  s.value
                FROM wa_contact_settings as f
                INNER JOIN wa_contact_settings as s
                  ON f.contact_id = s.contact_id
                    AND s.name = 'reminder_daily'
                    AND s.app_id = 'crm'
                LEFT JOIN wa_contact_settings as d
                  ON f.contact_id = d.contact_id
                     AND d.name='reminder_send_date'
                     AND d.app_id = 'crm'
                WHERE f.name = 'reminder_recap'
                    AND f.value = '1'
                    AND f.app_id = 'crm'
                    AND (d.value IS NULL OR CAST(d.value as datetime) < ?)";

        $res = $this->query($sql, array($date));

        return $res->fetchAll();
    }


    public function getReminders($id, $date) {
        $sql = "SELECT
                  due_date,
                  due_datetime,
                  content
                FROM crm_reminder
                WHERE user_contact_id = ?
                  AND complete_datetime IS NULL
                  AND CAST(due_date as datetime) <= ?";

        $res = $this->query($sql, array($id, $date));

        return $res->fetchAll();
    }

    /**
     *
     * @param null|int $user_id
     *
     * @param array $options If empty (or skipped) extract all possible counters,
     *      otherwise not empty key dictates extract this type of counter (which is coded by key)
     *      Possible type of counters: 'overdue', 'burn', 'actual', 'total'
     * For example:
     * @example getUsersCounts(1, array('overdue' => true, 'actual' => true)) // get overdue and actual counters
     * @example getUsersCounts(1, array('actual' => true))  // get actual counter
     * @example getUsersCounts(1)  // get all possible counters
     *
     * @return array $result
     *   int $result['id'] - user contact id
     *   int $result['count'] - total count (if pass not empty value by key 'total' OR pass empty (or skipped) $options)
     *   int $result['due_count'] - count of "overdue" reminders
     *   int $result['burn_count'] - count of "burn" reminders
     *   int $result['burn_count'] - count of "actual" reminders
     */
    public function getUsersCounts($user_id = null, $options = array())
    {

        $cond = $user_id ? "AND user_contact_id = ".(int)$user_id : '';

        $select = array(
            "user_contact_id AS id"
        );

        if (empty($options) || !empty($options['total'])) {
            $select[] = "COUNT(*) AS `count`";
        }

        if (empty($options) || !empty($options['overdue'])) {
            $overdue_count_condition = 'due_datetime < :dt OR due_date < :d';
            $select[] = "SUM(IF({$overdue_count_condition}, 1, 0)) AS `due_count`";
        }

        if (empty($options) || !empty($options['burn'])) {
            $burn_count_condition = 'due_date = :d AND (due_datetime IS NULL OR due_datetime >= :dt)';
            $select[] = "SUM(IF({$burn_count_condition}, 1, 0)) AS `burn_count`";
        }

        if (empty($options) || !empty($options['actual'])) {
            $actual_count_condition = 'due_date = :d_tomorrow';
            $select[] = "SUM(IF({$actual_count_condition}, 1, 0)) AS `actual_count`";
        }

        $select = join(', ', $select);

        $sql = "SELECT {$select}
                FROM {$this->getTableName()}
                WHERE complete_datetime IS NULL $cond
                GROUP BY user_contact_id";
        $res = $this->query($sql, array(
            'dt'         => date('Y-m-d H:i:s'),
            'd'          => date('Y-m-d'),
            'd_tomorrow' => date('Y-m-d', strtotime('+1 day')),
        ));

        if (!$user_id) {
            return $res->fetchAll('id', true);
        } else {
            return $res->fetchAssoc();
        }
    }
    public function getUpcoming() {
        $user_id    = wa()->getUser()->getId();
        $pop_up_min = wa()->getUser()->getSettings('crm', 'reminder_pop_up_min');

        if ($pop_up_min < 1) {
            $pop_up_min = self::POP_UP_MIN;
        }

            $begin_date = date('Y-m-d H:i:s');
            $end_date   = date('Y-m-d H:i:s', strtotime('+'.$pop_up_min.' minutes'));

            $sql = "SELECT *
                FROM `crm_reminder`
                WHERE `user_contact_id` = ?
                    and `complete_datetime` IS NULL
                    and `due_datetime` IS NOT NULL
                    and `due_datetime` BETWEEN ? AND ?";

            $result = $this->query($sql, $user_id, $begin_date, $end_date);
        return $result;
    }
}
