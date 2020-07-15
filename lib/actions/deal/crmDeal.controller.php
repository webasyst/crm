<?php
/**
 * List of deals.
 * Selects one of two possible views: by funnel stages or a flat table.
 * See crmDealFunnelAction (by stages), crmDealListAction (table).
 */
class crmDealController extends waViewController
{
    public function execute()
    {
        $view = waRequest::get('view', null, waRequest::TYPE_STRING_TRIM);
        if ($view) {
            wa()->getUser()->setSettings(wa()->getApp(), 'deal_view_mode', $view);
        } else {
            $view = wa()->getUser()->getSettings(wa()->getApp(), 'deal_view_mode');
        }

        try {
            if ($view == 'list') {
                $this->executeAction(new crmDealListAction());
            } else {
                $this->executeAction(new crmDealFunnelAction());
            }
        } catch (crmNoFunnelsException $e) {
            $this->executeAction(new crmDealNoFunnelAction());
        }
    }
}
