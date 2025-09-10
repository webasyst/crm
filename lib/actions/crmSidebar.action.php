<?php

class crmSidebarAction extends crmViewAction
{
    public function execute()
    {
        $this->sidebar();
        if (wa('crm')->whichUI('crm') === '1.3') {
            $this->setTemplate('templates/actions-legacy/Sidebar.html');
        } else {
            $this->setTemplate('templates/actions/Sidebar.html');
        }
    }

    public function sidebar()
    {
        $user = wa()->getUser();

        // Has to come before other assigns because it messes up with smarty vars
        $this->view->assign('backend_sidebar', $this->pluginHook());

        $wcm = new waContactModel();
        $im = new crmInvoiceModel();
        $rm = new crmReminderModel();

        $reminders_state = 'normal';
        $reminders_counts = $rm->getUsersCounts($user->getId());
        if (!empty($reminders_counts['due_count'])) {
            $reminders_state = 'overdue';
        } elseif (!empty($reminders_counts['burn_count'])) {
            $reminders_state = 'burn';
        } elseif (!empty($reminders_counts['actual_count'])) {
            $reminders_state = 'actual';
        }
        $can_manage_invoices = $user->getRights('crm', 'manage_invoices');

        $contact_max_id = waRequest::cookie('contact_max_id', 0, waRequest::TYPE_INT);

        $invoice_max_id = waRequest::cookie('invoice_max_id', 0, waRequest::TYPE_INT);
        $reminder_max_id = waRequest::cookie('reminder_max_id', 0, waRequest::TYPE_INT);

        $this->view->assign(array(
            'contacts_count'      => $wcm->countAll(), // $collection->count(),
            'contacts_new_count'  => ($contact_max_id && wa('crm')->whichUI('crm') === '1.3') 
                ? $wcm->select('COUNT(*) cnt')->where("id > $contact_max_id")->fetchField('cnt') 
                : 0, // show new contacts counter only for UI 1.3
            'reminders_count'     => ifset($reminders_counts, 'count', 0),
            'reminders_state'     => $reminders_state,
            'reminders_due_count' => ifset($reminders_counts, 'due_count', 0) + ifset($reminders_counts, 'burn_count', 0),
            'recent'              => $this->getRecent(),
            'can_manage_invoices' => $can_manage_invoices,
            'recent_block_hidden' => $user->getSettings('crm', 'sidebar_recent_block_hidden', '0'),
            'menu_state'          => $user->getSettings('crm', 'sidebar_menu_state', 'expanded'),
            'is_premium'          => crmHelper::isPremium(),
            'is_reload'           => boolval(waRequest::get('reload', 0, waRequest::TYPE_INT))
        ));

        if (wa('crm')->whichUI('crm') === '1.3') {
            $this->view->assign([
                'reminders_new_count' => $reminder_max_id ? $rm->select('COUNT(*) cnt')->where(
                    "complete_datetime IS NULL AND id > $reminder_max_id AND user_contact_id=".(int)$user->getId()
                )->fetchField('cnt') : 0,
            ]);
        }

        if ($can_manage_invoices) {
            $this->view->assign(array(
                'invoices_count' => $im->getList(array(
                    'count_results' => 'only',
                    'check_rights' => true,
                )),
                'invoices_new_count'  => $invoice_max_id ? $im->getList(array(
                    'min_id' => $invoice_max_id,
                    'count_results' => 'only',
                    'check_rights' => true,
                )) : 0,
            ));
        }

        $this->assignMessagesSectionVars();
        $this->assignDealSectionVars();
        $this->assignCallSectionVars();
    }

    private function getRecent()
    {
        if (wa('crm')->whichUI('crm') !== '1.3') {
            // TODO: implement recent block for 2.0
            return null;
        }
        $limit = 10;

        $rm = new crmRecentModel();
        $dm = new crmDealModel();
        $recent = $rm->select('*')->where('user_contact_id='.(int)wa()->getUser()->getId())->order(
            'is_pinned DESC, view_datetime DESC'
        )->fetchAll();

        $contact_ids = $deal_ids = array();
        foreach ($recent as &$r) {
            if ($r['contact_id'] > 0) {
                $contact_ids[$r['contact_id']] = 1;
            } else {
                $deal_ids[(int)abs($r['contact_id'])] = 1;
            }
        }
        if ($deal_ids) {
            $deals = $dm->select('id,name,contact_id,amount,currency_id')->where(
                "id IN('".join("','", array_keys($deal_ids))."')"
            )->fetchAll('id');
            foreach ($deals as $d) {
                $contact_ids[$d['contact_id']] = 1;
            }
        }
        if (!$contact_ids) {
            return null;
        }
        $collection = new waContactsCollection('/id/'.join(',', array_keys($contact_ids)));
        $contacts = $collection->getContacts(wa('crm')->getConfig()->getContactFields());

        $out = $not_shown = array();
        $count = 0;
        foreach ($recent as &$r) {
            if ($r['contact_id'] > 0) {
                if (isset($contacts[$r['contact_id']])) {
                    $r['deal'] = null;
                    $r['contact'] = $contacts[$r['contact_id']]; // new waContact($contacts[$r['contact_id']]);
                    $r['name'] = $contacts[$r['contact_id']]['name'];
                    $r['uri'] = 'contact/'.$r['contact_id'].'/';
                    $out[] = $r;
                    if (!$r['is_pinned']) {
                        $count++;
                    }
                } else {
                    $this->deleteRecent($r);
                }
            } else {
                $deal_id = abs($r['contact_id']);
                if (isset($deals[$deal_id])) {
                    $r['deal'] = $deals[$deal_id];
                    if ($r['deal']['contact_id'] && isset($contacts[$r['deal']['contact_id']])) {
                        $r['contact'] = $contacts[$r['deal']['contact_id']]; // new waContact($contacts[$r['deal']['contact_id']]);
                        $r['name'] = $r['deal']['name'];
                        $r['uri'] = 'deal/'.$deal_id.'/';
                        $out[] = $r;
                        if (!$r['is_pinned']) {
                            $count++;
                        }
                    } else {
                        $this->deleteRecent($r);
                    }
                } else {
                    $this->deleteRecent($r);
                }
            }
            if ($count > $limit) {
                $this->deleteRecent($r);
                array_pop($out);
            }
        }
        unset($r);
        return $out;
    }

    private function deleteRecent($row)
    {
        $rm = new crmRecentModel();
        $rm->deleteByField(array('user_contact_id' => $row['user_contact_id'], 'contact_id' => $row['contact_id']));
    }

    protected function pluginHook()
    {
        $event_params = array();
        if (wa('crm')->whichUI('crm') === '1.3') {
            $backend_sidebar = wa('crm')->event('backend_sidebar', $event_params, [
                'top_li',
                'middle_li',
                'bottom_li',
            ]);
        } else {
            $backend_sidebar = wa('crm')->event('backend_sidebar20', $event_params, [
                'top_li',
                'bottom_li'
            ]);
        }

        return array_filter($backend_sidebar, 'is_array');
    }

    protected function assignCallSectionVars()
    {
        $right = wa()->getUser()->getRights('crm', 'calls');
        if ($right == crmRightConfig::RIGHT_CALL_NONE) {
            $this->view->assign('calls_has_access', false);
            return;
        }

        $cm = new crmCallModel();

        $count = $cm->getList(array(
            'check_rights' => true,
            'count_results' => 'only',
        ));

        $new_count = 0;
        $call_max_id = (int)waRequest::cookie('call_max_id');
        if ($call_max_id > 0) {
            $new_count = $cm->getList(array(
                'check_rights' => true,
                'count_results' => 'only',
                'max_id' => $call_max_id
            ));
        }

        $this->view->assign(array(
            'calls_has_access' => true,
            'calls_count' => $count,
            'calls_new_count' => $new_count
        ));
    }

    protected function assignMessagesSectionVars()
    {
        $user = wa()->getUser();

        $mm = new crmMessageModel();
        $messages_count = $mm->getList(array(
            'count_results' => 'only',
            'cache' => 60,
            'check_rights' => true
        ));

        $messages_new_count = 0;
        $messages_max_id = (int)$user->getSettings("crm", "messages_max_id", "0");
        if ($messages_max_id > 0) {
            $messages_new_count = $mm->getList(array(
                'count_results' => 'only',
                'cache' => 60,
                'check_rights' => true,
                'min_id' => $messages_max_id
            ));
        }

        $this->view->assign(array(
            'messages_count'      => $messages_count,
            'messages_new_count'  => $messages_new_count,
        ));
    }

    protected function assignDealSectionVars()
    {
        // check access to deal list page
        $fm = new crmFunnelModel();
        $funnels = $fm->getAllFunnels();
        if (!$funnels && !wa()->getUser()->isAdmin('crm')) {
            $this->view->assign('deals_has_access', false);
            return;
        }

        
        $unpinned_funnels = wa()->getUser()->getSettings('crm', 'unpinned_funnels');
        $unpinned_funnels = empty($unpinned_funnels) ? [] : explode(',', $unpinned_funnels);
        $funnels = array_filter($funnels, function($funnel) use ($unpinned_funnels) {
            return !in_array($funnel['id'], $unpinned_funnels);
        });

        $dm = new crmDealModel();
        $deal_max_id = waRequest::cookie('deal_max_id', 0, waRequest::TYPE_INT);

        $funnels = array_map(function($funnel) use ($dm) {
            $funnel['deals_count'] = $dm->countOpen(['funnel_id' => $funnel['id']]);
            return $funnel;
        }, $funnels);

        $this->view->assign(array(
            'deals_has_access' => true,
            'deals_count'      => $dm->countOpen(),
            'deals_new_count'  => $deal_max_id ? crmDeal::getNewCount($deal_max_id) : 0,
            'funnels'          => $funnels,
        ));
    }
}
