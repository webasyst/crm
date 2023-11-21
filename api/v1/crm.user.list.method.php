<?php

class crmUserListMethod extends crmApiAbstractMethod
{
    public function execute()
    {
        $userpic_size = waRequest::get('userpic_size', 32, waRequest::TYPE_INT);
        $can_own_contact_id = waRequest::get('can_own_contact', null, waRequest::TYPE_INT);
        $can_own_deal_id = waRequest::get('can_own_deal', null, waRequest::TYPE_INT);
        $with_rights = waRequest::get('with_rights', 0, waRequest::TYPE_INT);

        $rights_model = new waContactRightsModel();
        $crm_user_ids = $rights_model->getUsers('crm');
        $crm_users = $this->getContactsMicrolist(
            $crm_user_ids,
            ['id', 'name', 'userpic'],
            $userpic_size
        );

        if ($can_own_contact_id) {
            $can_own_contact = new crmContact($can_own_contact_id);
            if (!$can_own_contact->exists()) {
                throw new waAPIException('not_found', _w('Contact not found'), 404);
            }
            $vault_id = $can_own_contact->get('crm_vault_id');
            if ($vault_id > 0) {
                $user_rights = $rights_model->getByIds($crm_user_ids, 'crm', 'vault.'.$vault_id);
                for ($i = count($crm_users) - 1; $i >= 0; $i--) {
                    if (empty($user_rights[$crm_users[$i]['id']])) {
                        unset($crm_users[$i]);
                    }
                }
            }
        }
        if ($can_own_deal_id) {
            $can_own_deal = $this->getDealModel()->getById($can_own_deal_id);
            if (empty($can_own_deal) || empty($can_own_deal['funnel_id'])) {
                throw new waAPIException('not_found', _w('Deal not found'), 404);
            }
            $funnel_rights = $rights_model->getByIds($crm_user_ids, 'crm', 'funnel.'.$can_own_deal['funnel_id']);
            for ($i = count($crm_users) - 1; $i >= 0; $i--) {
                if (empty($funnel_rights[$crm_users[$i]['id']])) {
                    unset($crm_users[$i]);
                }
            }
        }

        if ($with_rights) {
            $rights = $this->getUserRights($crm_users);
        }

        $result = [];
        $names = array_column($crm_users, 'name');
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($names as $_name) {
            foreach ($crm_users as $i => $_user) {
                if ($_name === $_user['name']) {
                    $result[] = $_user + ifset($rights, $_user['id'], []);
                    unset($crm_users[$i]);
                    break;
                }
            }
        }

        $this->response = $result;
    }

    private function getUserRights($crm_users)
    {
        $rights = [];
        if (empty($crm_users)) {
            return $rights;
        }
        foreach ($crm_users as $_crm_user) {
            /**
             * @TODO необходимо переработать получение прав всех юзеров сразу (оптом), а не поштучно.
             * На текущий момент инструментария получения всех прав юзеров нет.
             */
            $user = new crmContact($_crm_user);
            if ($user->isAdmin()) {
                $u_right = ['is_full' => true];
            } else {
                $user_rights = $user->getRights('crm');
                if (ifset($user_rights, 'backend', 0) >= 2) {
                    $u_right = ['is_full' => true];
                } else {
                    $u_right = ['is_full' => false];

                    /** vaults */
                    if ($vaults = $user->getRights('crm', 'vault.%')) {
                        $u_right['vaults'] = array_keys($vaults);
                    }

                    /** funnels */
                    if ($funnels = $user->getRights('crm', 'funnel.%')) {
                        $lvl = [
                            crmRightConfig::RIGHT_FUNNEL_NONE => 'NO',
                            crmRightConfig::RIGHT_FUNNEL_OWN => 'OWN',
                            crmRightConfig::RIGHT_FUNNEL_OWN_UNASSIGNED => 'OWN+FREE',
                            crmRightConfig::RIGHT_FUNNEL_ALL => 'FULL'
                        ];
                        foreach ($funnels as $_f_id => $_level) {
                            $fun[] = [
                                'funnel_id' => $_f_id,
                                'level'     => ifset($lvl, $_level, '')
                            ];
                        }
                        $u_right['funnels'] = ifset($fun, []);
                    }

                    /** invoices */
                    if (isset($user_rights['manage_invoices'])) {
                        $invoices = [
                            crmRightConfig::RIGHT_INVOICES_NONE => 'NO',
                            crmRightConfig::RIGHT_INVOICES_OWN => 'OWN',
                            crmRightConfig::RIGHT_INVOICES_ALL => 'FULL'
                        ];
                        $u_right['invoices'] = ifset($invoices, $user_rights['manage_invoices'], '');
                    }

                    /** calls */
                    if (isset($user_rights['calls'])) {
                        $calls = [
                            crmRightConfig::RIGHT_CALL_NONE => 'NO',
                            crmRightConfig::RIGHT_CALL_OWN => 'OWN',
                            crmRightConfig::RIGHT_CALL_ALL => 'FULL'
                        ];
                        $u_right['calls'] = ifset($calls, $user_rights['calls'], '');
                    }

                    /** conversations */
                    if (isset($user_rights['conversations'])) {
                        $conversations = [
                            crmRightConfig::RIGHT_CONVERSATION_OWN => 'OWN',
                            crmRightConfig::RIGHT_CONVERSATION_OWN_OR_FREE => 'OWN+FREE',
                            crmRightConfig::RIGHT_CONVERSATION_ALL => 'FULL'
                        ];
                        $u_right['conversations'] = ifset($conversations, $user_rights['conversations'], '');
                    }
                }
            }
            $rights[$_crm_user['id']]['rights'] = $u_right;
        }

        return $rights;
    }
}
