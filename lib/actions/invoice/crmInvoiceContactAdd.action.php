<?php
/**
 * HTML for dialog to manage invoice client contact.
 */
class crmInvoiceContactAddAction extends crmBackendViewAction
{
    function execute()
    {
        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            $this->accessDenied();
        }
        return parent::execute();
    }
}
