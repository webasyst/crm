<?php

class crmContactDeleteMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_DELETE;

    public function execute()
    {
        $contact_ids = (array) $this->get('id', true);

        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (empty($contact_ids)) {
            throw new waAPIException('empty_id', sprintf_wp('Missing required parameter: “%s”.', 'id'), 400);
        }

        $operation = new crmContactOperationDelete(['contacts' => $this->getContactsMicrolist($contact_ids, ['id', 'name', 'is_user', 'crm_vault_id', 'crm_user_id', 'create_contact_id'])]);
        $result = $operation->execute();

        $count = ifset($result, 'count', 0);
        if ($count > 0) {
            $log_action = $count > 30 ? 'contacts_delete' : 'contact_delete';
            crmHelper::logAction($log_action, ifset($result, 'log_params', []));
        }

        $this->response = [
            'count'   => $count,
            'message' => sprintf(
                _w("%d contact has been deleted", "%d contacts have been deleted", $count),
                $count
            )
        ];
    }
}
