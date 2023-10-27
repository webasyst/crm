<?php
/**
 * Deals list page. View mode with columns by funnel stages.
 */
class crmDealFunnelAction extends crmDealListAction
{
    public function execute()
    {
        $this->view_mode = 'thumbs';
        parent::execute();
    }

    protected function getListParams($api = false)
    {
        $list_params = parent::getListParams($api);

        unset($list_params['stage_id']);
        $list_params['status_id'] = 'OPEN';

        $list_params['message_unread'] = waRequest::request('message_unread', null, waRequest::TYPE_STRING_TRIM);

        return $list_params;
    }
}
