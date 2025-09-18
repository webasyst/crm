<?php
class crmDefaultLayout extends waLayout
{
    public function execute()
    {
        $this->executeAction('sidebar', new crmSidebarAction());

        /**
         * Include plugins js and css
         * @event backend_assets
         * @return array[string]string $return[%plugin_id%] Extra head tag content
         */
        $this->view->assign('backend_assets', wa('crm')->event('backend_assets'));

        // API token for SPA
        $token = (new waApiTokensModel())->getToken(crmConfig::API_CLIENT_ID, wa()->getUser()->getId(), crmConfig::API_TOKEN_SCOPE);

        // Locale for SPA
        $locale = str_replace('_', '-', wa()->getLocale());

        list($contact_list_columns, $contact_list_sort, $deal_funnel_columns, $deal_list_filter, $deal_list_sort, $funnel_bricks) = $this->getContactListSettings();

        $this->view->assign([
            'spa_api_token'        => $token,
            'spa_locale'           => $locale,
            'can_init_call'        => waUtils::jsonEncode((new crmRights())->isInitCall()),
            'is_sms_configured'    => waUtils::jsonEncode($this->isSMSConfigured()),
            'is_email_configured'  => waUtils::jsonEncode($this->isEmailConfigured()),
            'contact_list_columns' => waUtils::jsonEncode($contact_list_columns),
            'contact_list_sort'    => waUtils::jsonEncode($contact_list_sort),
            'deal_funnel_columns'  => waUtils::jsonEncode($deal_funnel_columns),
            'deal_list_filter'     => waUtils::jsonEncode($deal_list_filter),
            'deal_list_sort'       => waUtils::jsonEncode($deal_list_sort),
            'funnel_bricks'        => waUtils::jsonEncode($funnel_bricks),
            'access_rights'        => waUtils::jsonEncode($this->getAccessRights()),
        ]);
    }

    private function getContactListSettings()
    {
        /** default value */
        $contact_list_columns = [
            ['field' => 'phone', 'width' => 'm'],
            ['field' => 'email', 'width' => 'l'],
            ['field' => 'tags', 'width' => 's'],
            ['field' => 'create_datetime', 'width' => 's'],
        ];
        $contact_list_sort = [
            'field' => 'create_datetime',
            'asc'   => false
        ];

        $csm = new waContactSettingsModel();
        $contact_settings = $csm->get(wa()->getUser()->getId(), 'crm');

        $funnel_model = new crmFunnelModel();
        $deal_funnel_columns = [];
        $funnels = $funnel_model->getAllFunnels(true);        
        foreach ($funnels as $funnel) {
            $_deal_columns = ifset($contact_settings, 'deal_funnel_columns:'.$funnel['id'], '');
            $_deal_list_columns = [];
            if (empty($_deal_columns)) {
                $_deal_list_columns = [
                    ['field' => 'amount'],
                    ['field' => 'user'],
                    ['field' => 'tags'],
                    ['field' => 'last_action'],
                ];
            } else {
                $_deal_columns = waUtils::jsonDecode($_deal_columns, true);
                foreach ($_deal_columns as $_column_name => $_list_column) {
                    if (empty($_list_column['off'])) {
                        $_deal_list_columns[] = [
                            'field' => (string) $_column_name,
                        ];
                    }
                }
            }

            $deal_funnel_columns[] = [
                'funnel_id' => $funnel['id'],
                'columns' => $_deal_list_columns,
            ];
        }

        $deal_list_sort = [
            'field' => 'create_datetime',
            'asc'   => false
        ];

        $list_columns = ifset($contact_settings, 'contact_list_columns', '');
        $list_sort = ifset($contact_settings, 'contacts_action_params', '');
        //$deal_columns = ifset($contact_settings, 'deal_list_columns', '');
        $deal_sort = ifset($contact_settings, 'deal_list_sort', '');
        if (!empty($list_columns)) {
            $contact_list_columns = [];
            $list_columns = waUtils::jsonDecode($list_columns, true);
            foreach ($list_columns as $_column_name => $_list_column) {
                if (empty($_list_column['off'])) {
                    $contact_list_columns[] = [
                        'field' => (string) $_column_name,
                        'width' => ifset($_list_column, 'width', '')
                    ];
                }
            }
        }
        if (strpos($list_sort, 'raw_sort') !== false) {
            $list_sort = waUtils::jsonDecode($list_sort, true);
            $list_sort = ifset($list_sort, 'raw_sort', $contact_list_sort);
            $contact_list_sort = [
                'field' => ifset($list_sort, 0, $contact_list_sort['field']),
                'asc'   => ifset($list_sort, 1, $contact_list_sort['asc']) == 'ASC'
            ];
        }
        /*
        if (!empty($deal_columns)) {
            $deal_list_columns = [];
            $deal_columns = waUtils::jsonDecode($deal_columns, true);
            foreach ($deal_columns as $_column_name => $_list_column) {
                if (empty($_list_column['off'])) {
                    $deal_list_columns[] = [
                        'field' => (string) $_column_name,
                    ];
                }
            }
        } */
        if (!empty($deal_sort)) {
            $deal_sort = explode(' ', $deal_sort);
            if (!empty($deal_sort[0]) && isset($deal_sort[1])) {
                $deal_list_sort = [
                    'field' => $deal_sort[0],
                    'asc'   => !!$deal_sort[1]
                ];
            }
        }
        $deal_list_filter = [
            'funnel_id' => $this->intOrNull($contact_settings, 'deal_funnel_id'),
            'stage_id'  => $this->intOrNull($contact_settings, 'deal_stage_id', ['won', 'lost']),
            'tag_id'    => $this->intOrNull($contact_settings, 'deal_tag_id'),
            'user_id'   => $this->intOrNull($contact_settings, 'deal_user_id')
        ];

        $funnel_bricks = $funnel_model->getAllFunnels();
        if (!empty($funnel_bricks)) {
            $unpinned_funnels = wa()->getUser()->getSettings('crm', 'unpinned_funnels');
            $unpinned_funnels = empty($unpinned_funnels) ? [] : explode(',', $unpinned_funnels);
            $funnel_bricks = array_filter($funnel_bricks, function($funnel) use ($unpinned_funnels) {
                return !in_array($funnel['id'], $unpinned_funnels);
            });
            $funnel_bricks = array_keys($funnel_bricks);
        }

        return [$contact_list_columns, $contact_list_sort, $deal_funnel_columns, $deal_list_filter, $deal_list_sort, $funnel_bricks];
    }

    protected function intOrNull($arr, $key, $allowed_str_values = [])
    {
        $result = ifset($arr, $key, '');
        if (is_numeric($result)) {
            return (int) $result;
        }
        if (in_array($result, $allowed_str_values)) {
            return $result;
        }
        return null;
    }

    protected function isSMSConfigured()
    {
        return waSMS::adapterExists();
    }

    protected function isEmailConfigured()
    {
        $csm = new crmSourceModel();
        $source = $csm->getActiveEmailSource();

        return !empty($source);
    }

    protected function getAccessRights()
    {
        $user = wa()->getUser();
        $crm_rights = $user->getRights('crm');
        $deal_access = false;
        if ($crm_rights) {
            foreach ($crm_rights as $name => $value) {
                if (($name == 'backend' && $value >= 2) || stripos($name, 'funnel') !== false) {
                    $deal_access = true;
                }
            }
        }
        return [
            'deal'     => $deal_access,
            'call'     => !($user->getRights('crm', 'calls') === crmRightConfig::RIGHT_CALL_NONE),
            'invoice'  => !!$user->getRights('crm', 'manage_invoices'),
            'export'   => !!$user->getRights('crm', 'export'),
            'is_admin' => $user->isAdmin('crm'),
        ];
    }
}
