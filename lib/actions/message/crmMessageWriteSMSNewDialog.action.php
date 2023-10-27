<?php

/**
 * Class crmMessageWriteSMSNewDialogAction
 *
 * Dialog to write SMS from contact context (contact page)
 */
class crmMessageWriteSMSNewDialogAction extends crmSendSMSDialogAction
{
    public function execute()
    {
        parent::execute();
        $iframe = waRequest::request('iframe', 0, waRequest::TYPE_INT);

        if (!empty($iframe) && wa('crm')->whichUI('crm') !== '1.3') {
            $this->setLayout();
        }

        $this->view->assign([
            'iframe' => $iframe
        ]);
    }

    public function getSendActionUrl()
    {
        return wa()->getAppUrl('crm') . '?module=message&action=sendSMSNew';
    }
}
