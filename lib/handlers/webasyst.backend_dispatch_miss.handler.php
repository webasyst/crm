<?php
class crmWebasystBackend_dispatch_missHandler extends waEventHandler
{
    public function execute(&$params)
    {
        $app = $params;
        if ($app !== 'contacts' || !wa()->getUser()->getRights('crm', 'backend')) {
            return;
        }

        // Idea is to redirect links to old contact profile to crm app
        // in case there's no Contacts app installed.
        //
        // Unfortunately, ID of the contact is only available client-side,
        // as browsers do not send #-hashes to the server.
        // We have to render a proxy page that will redirect user afterwards.

        include(wa('crm')->getAppPath('lib/handlers/redirectcontact.include.php'));
        return true;
    }
}
