<?php

class crmSpaFrameAction extends crmBackendViewAction
{
    public function execute()
    {
        $this->setLayout();
        $this->setTemplate('templates/actions/SpaFrame.html');

        // API token for SPA
        $token = (new waApiTokensModel())->getToken(crmConfig::API_CLIENT_ID, wa()->getUser()->getId(), crmConfig::API_TOKEN_SCOPE);

        // Locale for SPA
        $locale = str_replace('_', '-', wa()->getLocale());
        $this->view->assign([
            'spa_api_token'        => $token,
            'spa_locale'           => $locale,
            'is_contact_profile'   => strpos(waRequest::server('REQUEST_URI'), '/frame/contact/') !== false,
            'can_init_call'        => waUtils::jsonEncode((new crmRights())->isInitCall()),
            'is_sms_configured'    => waUtils::jsonEncode($this->isSMSConfigured()),
            'is_email_configured'  => waUtils::jsonEncode($this->isEmailConfigured()),
            'access_rights'        => waUtils::jsonEncode($this->getAccessRights()),
        ]);
    }

    public static function checkSkipUpdateLastPage()
    {
        waRequest::setParam('skip_update_last_page', '1');
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
