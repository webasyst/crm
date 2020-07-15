<?php

class crmContactsDeleteHandler extends waEventHandler
{
    /**
     * @param int[] $params Deleted contact_id
     * @see waEventHandler::execute()
     * @return void
     */
    public function execute(&$params)
    {
        $operation = new crmContactOperationDelete(array(
            'contacts' => $params
        ));
        $operation->deleteCrmLinks();
    }
}
