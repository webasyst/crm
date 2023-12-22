<?php

class crmWaLogExplainer
{
    protected $log_items;

    public function __construct($log_items)
    {
        $this->log_items = $log_items;
    }

    public function explain()
    {
        $log_items = $this->log_items;
        $this->decodeJsonParams($log_items);

        $app_info = wa()->getAppInfo();
        if ($app_info['id'] != 'crm') {
            wa('crm', true);
        }

        foreach ($log_items as &$log_item) {
            if ($this->isContactDeleteLogItem($log_item)) {
                $this->explainDeleteContactLogItem($log_item);
            }
        }
        unset($log_item);

        $this->explainLogItemsWithSubjectContactIds($log_items);

        if ($app_info['id'] != 'crm') {
            wa($app_info['id'], true);
        }

        return $log_items;

    }

    protected function decodeJsonParams(&$log_items)
    {
        foreach ($log_items as &$log_item) {
            if (!empty($log_item['params']) && !is_array($log_item['params'])) {
                $params = json_decode($log_item['params'], true);
                if (!empty($params)) {
                    $log_item['params'] = $params;
                }
            }
        }
    }

    protected function isContactDeleteLogItem($log_item)
    {
        $is_crm = isset($log_item['app_id']) && $log_item['app_id'] === 'crm';
        $is_delete_contact = isset($log_item['action']) && $log_item['action'] === 'contact_delete';
        return $is_crm && $is_delete_contact;
    }

    protected function explainLogItemsWithSubjectContactIds(&$log_items)
    {
        $subject_contact_ids = array();

        foreach ($log_items as $log_item) {
            $is_crm = isset($log_item['app_id']) && $log_item['app_id'] === 'crm';
            if ($is_crm && wa_is_int($log_item['subject_contact_id']) && $log_item['subject_contact_id'] > 0) {
                $subject_contact_ids[] = $log_item['subject_contact_id'];
            }
        }

        if (!$subject_contact_ids) {
            return;
        }

        $subject_contact_ids = array_unique($subject_contact_ids);
        $col = new crmContactsCollection('id/' . join(',', $subject_contact_ids));

        $contacts = $col->getContacts('id,name,firstname,lastname,middlename,company,is_user,login,email', 0, count($subject_contact_ids));

        $contact_names = crmContactsCollection::extractNames($contacts);

        $crm_app_url = wa()->getAppUrl('crm');

        foreach ($log_items as &$log_item) {

            $is_crm = isset($log_item['app_id']) && $log_item['app_id'] === 'crm';
            $has_subject_contact_id = wa_is_int($log_item['subject_contact_id']) && $log_item['subject_contact_id'] > 0;
            if (!$is_crm || !$has_subject_contact_id) {
                continue;
            }

            $contact_id = $log_item['subject_contact_id'];
            $is_contact_exists = isset($contact_names[$contact_id]);

            if (!$is_contact_exists) {
                $contact_link = null;
                $contact_name = sprintf(_w('“%s”'), _w('Deleted contact') . ' ' . $contact_id);
            } else {
                $contact_link = "{$crm_app_url}contact/{$contact_id}";
                $contact_name = trim($contact_names[$contact_id]);
                if (strlen($contact_name) <= 0) {
                    $contact_name = '(' . _w("no name") . ')';
                }
            }

            if ($contact_link) {
                $subject_contact_str = sprintf("<a href='%s'>%s</a>", $contact_link, htmlspecialchars($contact_name));
            } else {
                $subject_contact_str = htmlspecialchars($contact_name);
            }

            if ($this->isSentMessageOutLogItem($log_item)) {
                $log_item['params_html'] = $this->buildSentMessageOutExplanation($log_item, $subject_contact_str);
            } elseif (in_array($log_item['action'], ['call_in', 'call_out'])) {
                $log_item['action_name'] = sprintf($log_item['action_name'], ifset($log_item, 'params', 'duration', '0'));
                $log_item['params_html'] = $subject_contact_str;
            } else {
                $log_item['params_html'] = $subject_contact_str;
            }

        }
        unset($log_item);
    }

    protected function explainDeleteContactLogItem(&$log_item)
    {
        if (empty($log_item['params'])) {
            return;
        }

        $params = $log_item['params'];

        if (wa_is_int($params)) {
            $count = $params;
            $log_item['action_name'] = _w('has deleted');
            $log_item['params_html'] = _w('%d contact', '%d contacts', $count);
            return;
        }

        if (!is_array($params)) {
            return;
        }

        $contact_names = $params;

        $contact_names = array_values($contact_names);

        $max_n = 5;
        $count = count($contact_names);

        // wrap around quotes (take into account localization)
        $n = min($max_n, $count);
        for ($i = 0; $i < $n; $i++) {
            $name = $contact_names[$i];
            $name = trim($name);
            if (strlen($name) <= 0) {
                $name = '(' . _w("no name") . ')';
            }
            $contact_names[$i] = sprintf(_w('“%s”'), htmlspecialchars($name));
        }

        if ($count > 1) {
            $log_item['action_name'] = _w('has deleted contacts');
        } else {
            $log_item['action_name'] = _w('has deleted contact');
        }

        if ($count <= $max_n) {
            $log_item['params_html'] = join(', ', $contact_names);
        } elseif ($count > $max_n) {
            $slice_of_contact_names = array_slice($contact_names, 0, $max_n);
            $log_item['params_html'] = sprintf(_w('%s and %s more'), join(', ', $slice_of_contact_names), $count - $max_n);
        }
    }

    protected function isSentMessageOutLogItem(&$log_item)
    {
        if ($log_item['action'] !== 'message_sent' || empty($log_item['params']) || !is_array($log_item['params'])) {
            return false;
        }

        return isset($log_item['params']['direction']) &&
            $log_item['params']['direction'] == crmMessageModel::DIRECTION_OUT;
    }

    protected function buildSentMessageOutExplanation($log_item, $subject_contact_str)
    {

        $transport = isset($log_item['params']['transport']) ? $log_item['params']['transport'] : "unknown";
        $transport_str = $transport;

        if ($transport === crmMessageModel::TRANSPORT_EMAIL) {
            $transport_str = "email";
        } elseif ($transport === crmMessageModel::TRANSPORT_SMS) {
            $transport_str = _w("SMS");
        } elseif ($transport === crmMessageModel::TRANSPORT_IM) {
            if (!empty($log_item['params']['source_info']['provider_name'])) {
                $transport_str = $log_item['params']['source_info']['provider_name'];
            } elseif (!empty($log_item['params']['source_info']['provider'])) {
                $transport_str = $log_item['params']['source_info']['provider'];
            } elseif (!empty($log_item['params']['source_info']['type'])) {
                $transport_str = $log_item['params']['source_info']['type'];
            } else {
                $transport_str = _w("Instant messenger");
            }
        }
        $transport_str = htmlspecialchars($transport_str);

        if (!empty($log_item['params']['subject'])) {
            return sprintf(_w('%s “%s” to contact %s'), $transport_str, htmlspecialchars($log_item['params']['subject']), $subject_contact_str);
        } else {
            return sprintf(_w('%s to contact %s'), $transport_str, $subject_contact_str);
        }
    }
}
