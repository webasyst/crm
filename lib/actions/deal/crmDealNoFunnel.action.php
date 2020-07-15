<?php
/**
 * Shown by crmDealController when there are no funnels set up
 */
class crmDealNoFunnelAction extends crmBackendViewAction
{
    public function execute()
    {
        if (waRequest::request('create') && wa()->getUser()->isAdmin('crm')) {
            $inst = new crmInstaller();
            $inst->installFunnels();
            $this->redirect(wa()->getUrl().'deal/');
        }
    }
}
