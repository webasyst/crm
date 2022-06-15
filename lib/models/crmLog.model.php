<?php

class crmLogModel extends crmModel
{
    protected $table = 'crm_log';
    public $deals = array();
    public $funnels = array();
    public $stages = array();

    protected $phone_formatter;

    const OBJECT_TYPE_CONTACT = 'CONTACT';
    const OBJECT_TYPE_DEAL = 'DEAL';
    const OBJECT_TYPE_INVOICE = 'INVOICE';
    const OBJECT_TYPE_REMINDER = 'REMINDER';
    const OBJECT_TYPE_NOTE = 'NOTE';
    const OBJECT_TYPE_FILE = 'FILE';
    const OBJECT_TYPE_CALL = 'CALL';
    const OBJECT_TYPE_EMAIL = 'EMAIL';
    const OBJECT_TYPE_MESSAGE = 'MESSAGE';
    const OBJECT_TYPE_ORDER_LOG = 'ORDER_LOG';

    public function add($data)
    {
        $action = (string)ifset($data['action']);
        if (strlen($action) <= 0) {
            return false;
        }
        $data['action'] = $action;

        $object_type = (string)ifset($data['object_type']);
        if (!in_array('OBJECT_TYPE_'.$object_type, $this->getObjectTypes())) {
            return false;
        }

        $data['create_datetime'] = date('Y-m-d H:i:s');
        if (!isset($data['actor_contact_id'])) {
            $data['actor_contact_id'] = wa()->getUser()->getId();
        }
        $data['actor_contact_id'] = (int)$data['actor_contact_id'];
        $data['contact_id'] = (int)ifset($data['contact_id']);

        return $this->insert($data);
    }

    public function log($action, $contact_id = null, $object_id = null, $before = null, $after = null, $actor_contact_id = null)
    {
        if (preg_match('/^([^_]+)_/', $action, $m)) {
            $object_type = strtoupper(ifempty($m[1], $m[0]));
        } else {
            $object_type = strtoupper($action);
        }

        if (!in_array('OBJECT_TYPE_'.$object_type, $this->getObjectTypes())) {
            return false;
        }
        $data = array(
            'create_datetime'  => date('Y-m-d H:i:s'),
            'actor_contact_id' => $actor_contact_id ? $actor_contact_id : wa()->getUser()->getId(),
            'action'           => $action,
            'contact_id'       => $contact_id,
            'object_id'        => $object_id,
            'object_type'      => $object_type,
            'before'           => $before,
            'after'            => $after,
        );
        return $this->add($data);
    }

    public function getLogLive($list_params, $count = false)
    {
        $condition = array();
        if (!empty($list_params['action_type'])) {
            $condition[] = "object_type='".$this->escape($list_params['action_type'])."'";
        }
        if (!empty($list_params['user_id'])) {
            $condition[] = "actor_contact_id=".intval($list_params['user_id']);
        }
        $condition[] = "action NOT LIKE 'deal_order_%'";
        if ($count) {
            return $this->select('COUNT(*) cnt')->where($condition)->fetchAssoc('cnt');
        } else {
            if (!empty($list_params['max_id'])) {
                $condition[] = 'id < '.(int)$list_params['max_id'];
            }
            $condition = join(' AND ', $condition);
            $limit = ifempty($list_params['limit'], 50);
            $log = $this->select('*')->where($condition)->order('id DESC')->limit((int)$limit)->fetchAll('id');
        }
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();

        $this->funnels = $fm->getAllFunnels();
        $this->stages = $fsm->select('*')->order('funnel_id, number')->fetchAll('id');
        crmHelper::getFunnelStageColors($this->funnels, $this->stages);

        return $this->explainLog($log, 'live');
    }

    public function getLogLiveChart($filters, $chart_params)
    {
        $dm = new crmDealModel();
        $dpm = new crmDealParticipantsModel();

        $condition = array();
        if (!empty($filters['action_type'])) {
            $condition[] = "object_type='".$this->escape($filters['action_type'])."'";
        }
        if (!empty($filters['user_id'])) {
            $condition[] = "actor_contact_id=".intval($filters['user_id']);
        }
        if (!empty($filters['$user_id'])) {
            $user_id = intval($filters['$user_id']);
            $cond = array("contact_id=$user_id");
            $deals1 = $dpm->select('deal_id')->where("contact_id=$user_id")->fetchAll('deal_id');
            $deals2 = $dm->select('id')->where("contact_id=$user_id")->fetchAll('id');

            if ($deal_ids = array_keys($deals1 + $deals2)) {
                $cond = "contact_id IN ($user_id,-".join(",-", $deal_ids).")";

                $this->deals = $dm->select('id, name, funnel_id, stage_id')->where(
                    "id IN('".join("','", $dm->escape($deal_ids))."')"
                )->fetchAll('id');
            }
            $condition[] = $cond;
        }
        $condition[] = "create_datetime >='".$this->escape($chart_params['start_date'])." 00:00:00' AND create_datetime <='"
            .$this->escape($chart_params['end_date'])." 23:59:59'";

        $condition = join(' AND ', $condition);

        if ($chart_params['group_by'] == 'months') {
            $select = "DATE_FORMAT(create_datetime, '%Y-%m-01') d";
            $group_by = "YEAR(create_datetime), MONTH(create_datetime)";
            $step = '+1 month';
            $chart_params['start_date'] = date('Y-m-01', strtotime($chart_params['start_date']));
            $chart_params['end_date'] = date('Y-m-01', strtotime($chart_params['end_date']));
        } else {
            $select = "DATE(create_datetime) d";
            $group_by = "DATE(create_datetime)";
            $step = '+1 day';
        }
        $sql = "SELECT object_type t, $select, COUNT(*) cnt FROM {$this->getTableName()}
            WHERE $condition GROUP BY object_type, $group_by ORDER BY $group_by";

        $log = $this->query($sql)->fetchAll();

        $chart = array();
        foreach (wa('crm')->getConfig()->getLogType() as $action => $data) {
            $points = array();
            $d = $chart_params['start_date'];
            while ($d <= $chart_params['end_date']) {
                $val = 0;
                foreach ($log as $l) {
                    if ($l['t'] == strtoupper($action) && $l['d'] == $d) {
                        $val = $l['cnt'];
                    }
                }
                $points[] = array(
                    'date'  => $d,
                    'value' => $val
                );
                $d = date('Y-m-d', strtotime($step, strtotime($d)));
            }
            $chart[] = array(
                'id'    => $action,
                'name'  => $data['name'],
                'color' => $data['color'],
                'data'  => $points,
            );
        }
        return $chart;
    }

    public function getLog($id, $selected_filters, $max_id = 0, $limit = 50)
    {
        $dm = new crmDealModel();
        $dpm = new crmDealParticipantsModel();

        $id = intval($id);

        $context = $id > 0 ? 'contact' : 'deal';

        $condition1 = "contact_id=$id";
        $condition2 = " AND action <> 'reminder_add'";

        if ($id > 0) {
            $contact = new waContact($id);
            try {
                $create_datetime = $contact->get('create_datetime');
                $condition2 .= " AND create_datetime >= '".$this->escape($create_datetime)."'";
            } catch (waException $e) {
            }
        }

        $deals = array();
        if ($id > 0) {
            $deals1 = $dpm->select('deal_id')->where("contact_id='".$id."'")->fetchAll('deal_id');
            $deals2 = $dm->select('id')->where("contact_id='".$id."'")->fetchAll('id');

            if ($deal_ids = array_keys($deals1 + $deals2)) {
                $condition1 = "contact_id IN ($id,-".join(",-", $deal_ids).")";

                $this->deals = $dm->getList(array(
                    'check_rights' => true,
                    'id'           => $deal_ids,
                ));

                $deals = $this->deals;
                $fm = new crmFunnelModel();
                $fsm = new crmFunnelStageModel();
                $this->funnels = $fm->getAllFunnels();
                $this->stages = $fsm->select('*')->order('funnel_id, number')->fetchAll('id');
                crmHelper::getFunnelStageColors($this->funnels, $this->stages);
            }
        }
        if ($max_id) {
            $condition1 .= ' AND id < '.(int)$max_id;
        }

        $objects = array();
        if (!empty($selected_filters['reminders']['is_active'])) {
            $objects[] = self::OBJECT_TYPE_REMINDER;
        }
        if (!empty($selected_filters['notes']['is_active'])) {
            $objects[] = self::OBJECT_TYPE_NOTE;
        }
        if (!empty($selected_filters['files']['is_active'])) {
            $objects[] = self::OBJECT_TYPE_FILE;
        }
        if (!empty($selected_filters['invoices']['is_active'])) {
            $objects[] = self::OBJECT_TYPE_INVOICE;
        }
        if (!empty($selected_filters['deals']['is_active'])) {
            $objects[] = self::OBJECT_TYPE_DEAL;
        }
        if (!empty($selected_filters['contacts']['is_active'])) {
            $objects[] = self::OBJECT_TYPE_CONTACT;
        }
        if (!empty($selected_filters['messages']['is_active'])) {
            $objects[] = self::OBJECT_TYPE_MESSAGE;
        }
        if (!empty($selected_filters['calls']['is_active'])) {
            $objects[] = self::OBJECT_TYPE_CALL;
        }
        if (!empty($selected_filters['order_log']['is_active']) && crmShop::canExplainOrderLog()) {
            $objects[] = self::OBJECT_TYPE_ORDER_LOG;
        }

        if ($objects) {
            $condition2 .= " AND object_type IN('".join("','", $objects)."')";
        }
        if ($context == 'deal') {
            $condition1 .= " AND action <> 'deal_add'";
        }
        $condition1 .= " AND action NOT LIKE 'deal_order_%'";

        $log = $this->select('SQL_CALC_FOUND_ROWS *')->where($condition1.$condition2)->order('id DESC')->limit((int)$limit)->fetchAll('id');
        $count = $this->query('SELECT FOUND_ROWS()')->fetchField();
        $min_id = $this->select('MIN(id) mid')->where($condition1.$condition2)->fetchField('mid');
        reset($log);

        $log = $this->explainLog($log, $context, $deals);

        return array($log, $min_id, $count);
    }

    protected function getObjectTypes()
    {
        return $this->getConstants('OBJECT_TYPE_');
    }

    private function getMessages($message_ids)
    {
        if (!$message_ids) {
            return array();
        }

        $mm = $this->getMessageModel();

        $select = array();
        $fields = array_keys($mm->getMetadata());
        foreach ($fields as $field_id) {
            if ($field_id !== 'body') {
                $select[] = "`{$field_id}`";
            } else {
                $select[] = "IF(`transport` = 'SMS', `body`, '') AS `body`";
            }
        }

        $select = join(',', $select);

        $where = 'id IN (:ids)';
        $bind = array('ids' => $message_ids);
        $messages = $mm->select($select)->where($where, $bind)->fetchAll('id');
        foreach ($messages as $m) {
            // Mark all messages unread
            $messages[$m['id']]['read'] = 0;
            // Add all empty params array
            $messages[$m['id']]['params'] = array();
        }
        unset($m);

        $read_messages = $this->getMessageReadModel()->getReadStatus($message_ids, wa()->getUser()->getId());
        foreach ($read_messages as $rm => $v) {
            if (isset($messages[$rm])) {
                $messages[$rm]['read'] = 1;
            }
        }
        unset($rm);

        $messages_params = $this->getMessageParamsModel()->getParamsByMessage($message_ids);

        foreach ($messages_params as $id => $params) {
            if (isset($messages[$id])) {
                $messages[$id]['params'] = $params;
            }
        }
        unset($messages_params);

        // to defined access to messages
        $allowed = $this->getCrmRights()->dropUnallowedMessages($messages);
        foreach ($messages as &$message) {
            $message['can_view'] = !empty($allowed[$message['id']]);
        }
        unset($message);

        return $messages;
    }

    private function explainLog($log, $context, $deals = null)
    {
        $rm = new crmReminderModel();
        $nm = new crmNoteModel();
        $fm = new crmFileModel();
        $im = new crmInvoiceModel();
        $cm = new crmCallModel();

        $logs = wa('crm')->getConfig()->getLogActions();

        $can_explain_shop_order_log = crmShop::canExplainOrderLog();

        $reminder_ids  = array();
        $note_ids      = array();
        $file_ids      = array();
        $contact_ids   = array();
        $deal_ids      = array();
        $message_ids   = array();
        $invoice_ids   = array();
        $call_ids      = array();
        $order_log_ids = array();

        foreach ($log as &$l) {

            $l['action_name'] = isset($logs[$l['action']]['name']) ? _w($logs[$l['action']]['name']) : $l['action'];

            if (stripos($l['action'], 'reminder_') === 0) {
                $reminder_ids[$l['object_id']] = 1;
            } elseif (stripos($l['action'], 'note_') === 0) {
                $note_ids[$l['object_id']] = 1;
            } elseif (stripos($l['action'], 'file_') === 0) {
                $file_ids[$l['object_id']] = 1;
            } elseif (stripos($l['action'], 'message_') === 0) {
                $message_ids[$l['object_id']] = 1;
            } elseif (stripos($l['action'], 'invoice_') === 0) {
                $invoice_ids[$l['object_id']] = 1;
            } elseif (stripos($l['action'], 'call') === 0) {
                $call_ids[$l['object_id']] = 1;
            } elseif ($l['object_type'] == self::OBJECT_TYPE_ORDER_LOG && $can_explain_shop_order_log) {
                $order_log_ids[$l['object_id']] = 1;
            }

            if ($l['contact_id'] > 0) {
                $contact_ids[$l['contact_id']] = 1;
            } else {
                $deal_ids[abs($l['contact_id'])] = 1;
            }
        }

        $reminders = array();
        if ($reminder_ids) {
            $reminders = $rm->select('*')->where("id IN('".join("','", $rm->escape(array_keys($reminder_ids)))."')")->fetchAll('id');
        }

        $notes = array();
        if ($note_ids) {
            $notes = $nm->select('*')->where("id IN('".join("','", $nm->escape(array_keys($note_ids)))."')")->fetchAll('id');
        }

        $messages = array();
        if ($message_ids) {
            $messages = $this->getMessages(array_keys($message_ids));
        }

        $files = array();
        if ($file_ids) {
            $files = $fm->getFiles(array_keys($file_ids));
        }

        $invoices = array();
        if ($invoice_ids) {
            $invoices = $im->select('*')->where("id IN('".join("','", $fm->escape(array_keys($invoice_ids)))."')")->fetchAll('id');
        }

        $calls = array();
        if ($call_ids) {
            $calls = $cm->getList(array(
                'id' => array_keys($call_ids),
                'check_rights' => 2
            ));
        }

        foreach ($reminders as $r) {
            if ($r['contact_id'] < 0) {
                $deal_ids[abs($r['contact_id'])] = 1;
            }
        }
        foreach ($notes as $n) {
            if ($n['contact_id'] < 0) {
                $deal_ids[abs($n['contact_id'])] = 1;
            }
        }
        foreach ($files as $f) {
            if ($f['contact_id'] < 0) {
                $deal_ids[abs($f['contact_id'])] = 1;
            }
        }
        foreach ($messages as $m) {
            if ($m['deal_id']) {
                $deal_ids[$m['deal_id']] = 1;
            }
            $contact_ids[$m['contact_id']] = 1;
        }
        foreach ($invoices as $i) {
            if ($i['deal_id']) {
                $deal_ids[$i['deal_id']] = 1;
            }
        }
        foreach ($calls as $c) {
            if ($c['deal_id']) {
                $deal_ids[$c['deal_id']] = 1;
            }
        }

        if ($deal_ids) {
            if ($deals) {
                $this->deals = array_intersect_key($deals, $deal_ids);
                $deal_ids = array_diff_key($deal_ids, $deals);
            } else {
                $this->deals = array();
            }
            if ($deal_ids) {
                $dm = new crmDealModel();
                $this->deals += $dm->getList(array(
                    'check_rights' => true,
                    'id'           => array_keys($deal_ids),
                ));
            }
        }

        $contacts = array();
        if ($contact_ids) {
            $contacts_collection = new crmContactsCollection('id/'.join(',', array_keys($contact_ids)), array(
                'check_rights' => true,
            ));
            $contacts = $contacts_collection->getContacts('id,name');
        }

        $order_log = array();
        if ($order_log_ids && $can_explain_shop_order_log) {
            $olm = new shopOrderLogModel();
            $order_log = $olm->getLogById(array_keys($order_log_ids));
            shopOrderLogModel::explainLog($order_log);
        }

        $rights = new crmRights();

        $view = wa()->getView();
        $old_view_vars = $view->getVars();
        $view->clearAllAssign();

        foreach ($log as $id => &$l) {
            $l['link'] = '';
            $l['action_name'] = isset($logs[$l['action']]['name']) ? _w($logs[$l['action']]['name']) : $l['action'];
            if (stripos($l['action'], 'reminder_') === 0) {
                $l['content'] = $l['object_id'] && isset($reminders[$l['object_id']]['content'])
                    ? $reminders[$l['object_id']]['content'] : null;
                $l['rights'] = !empty($reminders[$l['object_id']])
                    ? $rights->reminderEditable($reminders[$l['object_id']]) : null;
                $l['reminder'] = ifset($reminders[$l['object_id']]);
                if (!empty($reminders[$l['object_id']])) {
                    $r = $reminders[$l['object_id']];
                    if ($r['contact_id'] < 0 && $context != 'deal') {
                        $l['deal'] = $this->getDeal(abs($r['contact_id']));
                    }
                } else {
                    $l['object_id'] = null;
                }
            } elseif (stripos($l['action'], 'note_') === 0) {
                $l['content'] = $l['object_id'] && isset($notes[$l['object_id']]['content'])
                    ? $notes[$l['object_id']]['content'] : null;
                if (!empty($notes[$l['object_id']])) {
                    $n = $notes[$l['object_id']];
                    if ($n['contact_id'] < 0 && $context != 'deal') {
                        $l['deal'] = $this->getDeal(abs($n['contact_id']));
                    }
                } else {
                    $l['object_id'] = null;
                }
            } elseif (stripos($l['action'], 'file_') === 0) {
                $l['file_size'] = $l['name'] = null;
                if (!empty($files[$l['object_id']])) {

                    $f = $files[$l['object_id']];

                    $l['name'] = $f['name'];
                    $path = $f['path'];
                    $l['file_size'] = file_exists($path) ? filesize($path) : 0;

                    if ($f['contact_id'] < 0) {
                        $l['deal'] = $this->getDeal(abs($f['contact_id']));
                    }
                } else {
                    $l['object_id'] = null;
                }
            } elseif (stripos($l['action'], 'deal_') === 0) {
                if ($l['before'] || $l['after']) {
                    $l['inline_html'] = '';
                    if (!empty($l['before'])) {
                        $l['inline_html'] .= htmlspecialchars($l['before']);
                    }
                    if (!empty($l['before']) && !empty($l['after'])) {
                        $l['inline_html'] .= ' &rarr; ';
                    }
                    if (!empty($l['after'])) {
                        $l['inline_html'] .= htmlspecialchars($l['after']);
                    }
                }
            } elseif (stripos($l['action'], 'contact_') === 0) {
                if ($l['before'] || $l['after']) {
                    $l['inline_html'] = '';
                    if (!empty($l['before'])) {
                        $l['inline_html'] .= htmlspecialchars($l['before']);
                    } else {
                        $l['inline_html'] .= '&lt;'._w('No owner').'&gt;';
                    }
                    $l['inline_html'] .= ' &rarr; ';
                    if (!empty($l['after'])) {
                        $l['inline_html'] .= htmlspecialchars($l['after']);
                    } else {
                        $l['inline_html'] .= '&lt;'._w('No owner').'&gt;';
                    }
                }
            } elseif (stripos($l['action'], 'message_') === 0) {
                if (!empty($messages[$l['object_id']])) {
                    $m = $messages[$l['object_id']];

                    if ($m['transport'] === crmMessageModel::TRANSPORT_SMS) {
                        $to_formatted = $this->formatPhone($m['to']);
                        $from_formatted = $this->formatPhone($m['from']);
                    } else {
                        $to_formatted = $m['to'];
                        $from_formatted = $m['from'];
                    }

                    if (empty($m['direction']) || $m['direction'] == 'OUT') {

                        $to = $to_formatted;
                        if (!empty($contacts[$m['contact_id']]['name'])) {
                            $to = htmlspecialchars($contacts[$m['contact_id']]['name']) . ' <span class="c-message-to">' . $to . '</span>';
                        }

                        $l['inline_html'] = '<i class="icon16 export-blue" title="'._w('outgoing').'"></i> '.sprintf_wp(
                                'to <span class="c-message-to-with-name">%s</span>',
                                $to ? $to : _w('unknown')
                            );
                    } else {

                        $from = $from_formatted;

                        $l['inline_html'] = '<i class="icon16 import" title="'._w('incoming').'"></i> '.sprintf_wp(
                                'from <span class="c-message-from">%s</span>',
                                $from ? $from : _w('unknown')
                            );
                    }

                    if ($m['source_id']) {
                        $source_helper = crmSourceHelper::factory(crmSource::factory($m['source_id']));
                        try {
                            $res = $source_helper->workupMessageLogItemHeader($m, $l);
                            $l = $res ? $res : $l;
                        } catch (waException $e) {
                        }
                    }

                    // show link if has access
                    $l['link'] = '';
                    if ($m['can_view']) {
                        if ($m['transport'] == crmMessageModel::TRANSPORT_EMAIL || $m['transport'] == crmMessageModel::TRANSPORT_IM) {
                            if ($context != 'live') {
                                $view->assign(array(
                                    'log'     => $l,
                                    'message' => $m,
                                ));
                                $l['link'] = $view->fetch('templates/actions/log/LogMessage.inc.html');
                                $view->clearAllAssign();
                            } else {
                                $l['link'] = htmlspecialchars($m['subject']);
                            }
                        } else {
                            // here is crmMessageModel::TRANSPORT_SMS case
                            $l['link'] = htmlspecialchars($m['body']);
                        }
                    }

                    if ($m['deal_id'] && $context != 'deal') {
                        $l['deal'] = $this->getDeal($m['deal_id']);
                    }
                } else {
                    $l['object_id'] = null;
                }
            } elseif (stripos($l['action'], 'call') === 0) {
                if (!empty($calls[$l['object_id']])) {
                    $c = $calls[$l['object_id']];

                    $phone = crmHelper::formatCallNumber($c);

                    if (empty($c['direction']) || $c['direction'] == 'IN') {
                        $l['inline_html'] = '<i class="icon16 import"></i> '.sprintf_wp(
                                'Incoming call from %s.',
                                $phone
                            );
                    } else {
                        $l['inline_html'] = '<i class="icon16 export-blue"></i> '.sprintf_wp(
                                'Outgoing call to %s.',
                                $phone
                            );
                    }
                    $status = wa('crm')->getConfig()->getCallStates($c['status_id']);
                    $l['inline_html'] .= ' '.sprintf_wp(
                            'Status: %s',
                            '<span style="color:'.$status['color'].'">'.$status['name'].'</span>'
                        );
                    if ($c['status_id'] != 'DROPPED') {
                        $l['inline_html'] .= ' '.sprintf_wp(
                                'Duration: %s',
                                crmHelper::formatSeconds($c['duration'])
                            );
                    }

                    // if no access, no link
                    if (!empty($c['has_access']) && !empty($c['record_attrs'])) {
                        $l['inline_html'] .= ' ' . crmHelper::getCallRecordLinkHtml($c);
                    }

                    if ($c['deal_id'] && $context != 'deal') {
                        $l['deal'] = $this->getDeal($c['deal_id']);
                    }
                } else {
                    $l['object_id'] = null;
                }
            } elseif ($l['object_type'] == self::OBJECT_TYPE_ORDER_LOG) {
                if (isset($order_log[$l['object_id']])) {
                    $l['order_log_item'] = $order_log[$l['object_id']];
                }
            }

            if ($context == 'live' && $l['contact_id']) { // && !empty($contacts[$l['contact_id']])) {
                if ($l['contact_id'] > 0) {
                    if (!empty($contacts[$l['contact_id']])) {
                        $c = new waContact($l['contact_id']);
                        $name = $c->getName(); // @TODO: $contacts[$l['contact_id']]['name']
                        // $name = $contacts[$l['contact_id']]['name'];
                        $l['link'] = '<div>'._w('Contact').': <a href="'.wa()->getAppUrl().'contact/'.$l['contact_id'].'/">'
                            .htmlspecialchars($name).'</a></div>'.$l['link'];
                    }
                }
            }
            if ($l['contact_id'] < 0 && !empty($this->deals[abs($l['contact_id'])]) && $context != 'deal') {
                $l['deal'] = $this->getDeal(abs($l['contact_id']));
            }
        }
        unset($l);

        $view->clearAllAssign();
        $view->assign($old_view_vars);

        return $log;
    }

    protected function formatPhone($phone)
    {
        if (!$phone) {
            return $phone;
        }
        if (!$this->phone_formatter) {
            class_exists('waContactPhoneField');
            $this->phone_formatter = new waContactPhoneFormatter();
        }
        return $this->phone_formatter->format(waContactPhoneField::cleanPhoneNumber($phone));
    }

    protected function getDeal($deal_id)
    {
        $deal = isset($this->deals[$deal_id]) ? $this->deals[$deal_id] : array();
        if ($deal) {
            $deal['funnel'] = ifset($this->funnels[ifset($deal['funnel_id'])]);
            $deal['stage'] = ifset($this->stages[ifset($deal['stage_id'])]);
        }
        if (!empty($deal['funnel']) && !empty($deal['stage'])) {
            return $deal;
        }
        return null;
    }
}
