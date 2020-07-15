<?php

/**
 * Class crmMessageWriteSMSNewDialogAction
 *
 * Dialog to write SMS from contact context (contact page)
 */
class crmMessageWriteSMSNewDialogAction extends crmSendSMSDialogAction
{
    public function getSendActionUrl()
    {
        return wa()->getAppUrl('crm') . '?module=message&action=sendSMSNew';
    }
}
