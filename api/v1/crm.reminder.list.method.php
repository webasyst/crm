<?php

class crmReminderListMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_GET;

    public function execute()
    {
        $is_my_only = !!waRequest::get('is_my_only', 0, waRequest::TYPE_INT);
        $user_id = $is_my_only ? wa()->getUser()->getId() : waRequest::get('user_id', 0, waRequest::TYPE_INT);
        $contact_id = waRequest::get('contact_id', 0, waRequest::TYPE_INT);
        $deal_id = waRequest::get('deal_id', 0, waRequest::TYPE_INT);
        $type = waRequest::get('type', null, waRequest::TYPE_STRING_TRIM);
        $due_date = waRequest::get('due_date', null, waRequest::TYPE_STRING_TRIM);
        $is_completed = waRequest::get('is_completed', 0, waRequest::TYPE_INT);
        $limit = waRequest::get('limit', crmConfig::ROWS_PER_PAGE, waRequest::TYPE_INT);
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);
        $reminder_ids = waRequest::get('id', [], waRequest::TYPE_ARRAY_INT);

        $where = $condition = [];
        if ($user_id > 0) {
            $condition['user_contact_id'] = $user_id;
            $where[] = 'user_contact_id = i:user_contact_id';
        }
        if ($contact_id > 0) {
            $condition['contact_id'] = $contact_id;
            $where[] = 'contact_id = i:contact_id';
        }
        if ($deal_id > 0) {
            $condition['contact_id'] = -1 * $deal_id;
            $where[] = 'contact_id = i:contact_id';
        }
        if (!empty($reminder_ids)) {
            $where[] = 'id IN ('.join(',', $reminder_ids).')';
        }
        if (!empty($type) && in_array($type, ['MEETING', 'CALL', 'MESSAGE', 'OTHER'])) {
            $condition['type'] = $type;
            $where[] = 'type = s:type';
        }
        if (!empty($due_date) && $this->validateDate($due_date)) {
            $condition['due_date'] = $due_date;
            $where[] = 'due_date <= s:due_date';
        }
        if ($is_completed == 0) {
            $where[] = 'complete_datetime IS NULL';
            $order = 'ISNULL(due_date), due_date, ISNULL(due_datetime), due_datetime';
        } else {
            $where[] = 'complete_datetime IS NOT NULL';
            $order = 'complete_datetime DESC';
        }

        $reminder_model = $this->getReminderModel();
        $reminders = $reminder_model->select('SQL_CALC_FOUND_ROWS *')
            ->where(join(' AND ', $where), $condition)
            ->order($order)
            ->limit("$offset, $limit")
            ->fetchAll();
        $total_count = (int) $reminder_model->query('SELECT FOUND_ROWS()')->fetchField();

        $deal_ids = [];
        $contact_ids = [];
        $user_ids = array_column($reminders, 'user_contact_id');
        $creator_ids = array_column($reminders, 'creator_contact_id');
        foreach (array_column($reminders, 'contact_id') as $_contact_id) {
            if ($_contact_id > 0) {
                $contact_ids[] = $_contact_id;
            } else if ($_contact_id < 0) {
                $deal_ids[] = $_contact_id * -1;
            }
        }
        $conts = $this->getContacts(array_merge($user_ids, $creator_ids, $contact_ids));
        $deals = $this->getDeals($deal_ids);
        foreach ($reminders as &$_reminder) {
            $_reminder['state'] = crmHelper::getReminderState($_reminder);
            $_reminder['can_edit'] = $this->getCrmRights()->reminderEditable($_reminder);
            $_reminder['user'] = ifempty($conts, $_reminder['user_contact_id'], null);
            $_reminder['creator'] = ifempty($conts, $_reminder['creator_contact_id'], null);
            if ($_reminder['contact_id'] > 0) {
                $_reminder['contact'] = ifempty($conts, $_reminder['contact_id'], null);
            } else if ($_reminder['contact_id'] < 0) {
                $_reminder['deal'] = ifempty($deals, $_reminder['contact_id'] * -1, null);
            }
            if (ifset($_reminder['due_datetime'])) {
                unset($_reminder['due_date']);
            } else {
                unset($_reminder['due_datetime']);
            }
        }

        $data = $this->filterData(
            $reminders,
            [
                'id',
                'create_datetime',
                'creator_contact_id',
                'contact_id',
                'user_contact_id',
                'due_date',
                'due_datetime',
                'complete_datetime',
                'content',
                'type',
                'state',
                'can_edit',
                'user',
                'creator',
                'contact',
                'deal'
            ],
            [
                'id' => 'integer',
                'creator_contact_id' => 'integer',
                'contact_id' => 'integer',
                'user_contact_id' => 'integer',
                'create_datetime' => 'datetime',
                'due_datetime' => 'datetime',
                'complete_datetime' => 'datetime'
            ]
        );

        $this->response = [
            'params' => [
                'total_count' => $total_count,
                'offset'      => $offset,
                'limit'       => $limit,
            ],
            'data' => $data
        ];
    }

    protected function validateDate($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    private function getContacts($contact_ids, $userpic_size = 32)
    {
        $result = [];
        if (!empty($contact_ids)) {
            $contacts = $this->getContactsMicrolist($contact_ids, ['id', 'name', 'userpic'], $userpic_size);
            foreach ($contacts as $contact) {
                $result[$contact['id']] = $contact;
            }
        }

        return $result;
    }

    private function getDeals($deal_ids)
    {
        if (empty($deal_ids)) {
            return null;
        }

        $deals = $this->getDealModel()->getList([
            'id' => $deal_ids,
            'check_rights' => true,
        ]);
        if ($deals) {
            $result = [];
            $funnels = $this->getFunnelModel()->getById(array_column($deals, 'funnel_id'));
            $funnels_with_stages = $this->getFunnelStageModel()->withStages($funnels);
            $contacts = $this->getContacts(array_column($deals, 'contact_id'));
            foreach ($deals as $deal) {
                $result[$deal['id']] = [
                    'id'          => (int) $deal['id'],
                    'name'        => $deal['name'],
                    'status_id'   => $deal['status_id'],
                    'amount'      => (float) $deal['amount'],
                    'currency_id' => $deal['currency_id'],
                    'contact'     => ifset($contacts, $deal['contact_id'], [])
                ];
                foreach ($funnels_with_stages as $_funn_with_st) {
                    if ($_funn_with_st['id'] == $deal['funnel_id']) {
                        $result[$deal['id']]['funnel'] = [
                            'id'    => (int) $_funn_with_st['id'],
                            'name'  => $_funn_with_st['name'],
                            'color' => $_funn_with_st['color']
                        ];
                        foreach ($_funn_with_st['stages'] as $_stage) {
                            if ($_stage['id'] == $deal['stage_id']) {
                                $result[$deal['id']]['stage'] = [
                                    'id'    => (int) $_stage['id'],
                                    'name'  => $_stage['name'],
                                    'color' => $_stage['color']
                                ];
                                break;
                            }
                        }
                        break;
                    }
                }
            }

            return $result;
        }

        return null;
    }
}
