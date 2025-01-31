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
        ]);
    }

    public static function checkSkipUpdateLastPage()
    {
        waRequest::setParam('skip_update_last_page', '1');
    }
}
