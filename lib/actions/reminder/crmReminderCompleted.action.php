<?php
/**
 * Load HTML with list of completed reminders of a user.
 */
class crmReminderCompletedAction extends crmBackendViewAction
{
    public function execute()
    {
        $user_id = waRequest::request('user_id', waRequest::param('user_id', wa()->getUser()->getId(), waRequest::TYPE_INT));
        $min_dt = waRequest::request('min_dt', 0, waRequest::TYPE_STRING_TRIM);
        $contact_id = waRequest::get('contact', null, waRequest::TYPE_INT);
        $deal_id = abs(waRequest::get('deal', 0, waRequest::TYPE_INT));

        $limit = 50;
        $contacts = [];
        $contact_ids = [];
        $deal_ids = [];
        $deals = [];
        $dm = new crmDealModel();
        $rm = new crmReminderModel();
        if ($user_id === 'all') {
            $where = 'complete_datetime IS NOT NULL';
        } else {
            $where = 'user_contact_id = '.$user_id.' AND complete_datetime IS NOT NULL';
        }
        if ($deal_id) {
            $where .= ' AND contact_id = '.((int) $deal_id * -1);
        } elseif ($contact_id) {
            $deals = $dm->select('id')->where('contact_id = ?', $contact_id)->fetchAll('id');
            $deal_ids = array_map(function ($_deal_id) {return $_deal_id * -1;}, array_keys($deals));
            $where .= ' AND contact_id IN ('.implode(',', [$contact_id] + $deal_ids).')';
        }
        $completed_reminders_count = $rm->select('COUNT(*) cnt')
            ->where($where)
            ->fetchField('cnt');

        if ($min_dt) {
            $where .= " AND complete_datetime < '".$rm->escape($min_dt)."'";
        }

        $reminders = $rm->select('*')
            ->where($where)
            ->order('complete_datetime DESC')
            ->limit($limit)
            ->fetchAll('id');

        foreach ($reminders as $id => &$r) {
            $contact_ids[$r['user_contact_id']] = 1;
            if ($r['contact_id'] > 0) {
                $contact_ids[$r['contact_id']] = 1;
            } elseif ($r['contact_id'] < 0) {
                $deal_ids[abs($r['contact_id'])] = 1;
            }
            $r['state'] = crmHelper::getReminderState($r, true);
            $r['title'] = crmHelper::getReminderTitle($r['state'], $r['due_date']);
            $r['rights'] = $this->getCrmRights()->reminderEditable($r);
        }
        unset($r);

        if ($deal_ids) {
            $deals = $dm->getList([
                'id'           => array_keys($deal_ids),
                'check_rights' => true
            ]);
            foreach ($deals as $d) {
                if ($d['contact_id']) {
                    $contact_ids[$d['contact_id']] = 1;
                }
            }
        }
        if ($contact_ids) {
            $collection = new waContactsCollection('/id/'.join(',', array_keys($contact_ids)));
            $contacts = $collection->getContacts(wa('crm')->getConfig()->getContactFields(), 0, count($contact_ids));
            foreach ($contacts as $c) {
                $contacts[$c['id']] = new waContact($c);
            }
        }
        $this->view->assign(array(
            'reminders'       => $reminders,
            'completed_reminders_count' => $completed_reminders_count,
            'contacts'        => $contacts,
            'deals'           => $deals,
            'completed_limit' => $limit,
        ));
    }
}
