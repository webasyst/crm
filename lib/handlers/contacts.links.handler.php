<?php

/**
 * This handler is triggered when one or more contacts will be deleted
 */
class crmContactsLinksHandler extends waEventHandler
{
    /**
     * @param array $params deleted contact_id
     * @return array|void
     * @throws waException
     */
    public function execute(&$params)
    {
        waLocale::loadByDomain('crm');
        $operation = new crmContactOperationDelete(array(
            'contacts' => $params
        ));
        return $operation->getCrmLinks();
    }
}
