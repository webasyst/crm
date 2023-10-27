<?php

/**
 * Deals list page. Flat table view mode.
 *
 * This is also a base class for some other controllers in this module.
 */
class crmDealListAction extends crmBackendViewAction
{
    use crmDealListTrait;

    /**
     * crmDealListAction constructor.
     * @param null $params
     * @throws crmAccessDeniedException
     */
    public function __construct($params = null)
    {
        parent::__construct($params);

        // check access to deal list page
        $fm = new crmFunnelModel();
        $funnels = $fm->getAllFunnels();
        if (!$funnels && !wa()->getUser()->isAdmin('crm')) {
            $this->accessDenied();
        }
    }

    public function execute()
    {
        $this->user_id = waRequest::request('user', wa()->getUser()->getSettings('crm', 'deal_user_id'), waRequest::TYPE_STRING_TRIM);
        $this->view->assign($this->prepareData());
        wa('crm')->getConfig()->setLastVisitedUrl('deal/');
    }
}
