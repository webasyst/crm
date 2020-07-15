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

        $rm = new crmReminderModel();

        $contacts = $contact_ids = $deal_ids = $deals = array();

        $where = 'user_contact_id = '.$user_id.' AND complete_datetime IS NOT NULL';
        if ($min_dt) {
            $where .= " AND complete_datetime < '".$rm->escape($min_dt)."'";
        }
        $limit = 50;

        $reminders = $rm->select('*')->where($where)->order('complete_datetime DESC')->limit((int)$limit)->fetchAll('id');

        $reminders_count = $rm->select('COUNT(*) cnt')->where('user_contact_id = '.$user_id
            .' AND complete_datetime IS NOT NULL')->fetchField('cnt');

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
            $dm = new crmDealModel();
            $deals = $dm->select('id, name, contact_id')->where(
                "id IN('".join("','", $dm->escape(array_keys($deal_ids)))."')"
            )->fetchAll('id');
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
            'reminders_count' => $reminders_count,
            'contacts'        => $contacts,
            'deals'           => $deals,
        ));
    }
}
