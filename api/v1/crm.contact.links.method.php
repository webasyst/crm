<?php

class crmContactLinksMethod extends crmApiAbstractMethod
{
    public function execute()
    {
        $contact_ids = waRequest::get('id', [], waRequest::TYPE_ARRAY_INT);
        if (empty($contact_ids)) {
            throw new waAPIException('empty_id', sprintf_wp('Missing required parameter: “%s”.', 'id'), 400);
        }

        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        $contacts = $this->getContactsMicrolist($contact_ids, ['id', 'name', 'userpic'], self::USERPIC_SIZE);
        $operation = new crmContactOperationDelete([
            'contacts' => array_combine(array_column($contacts, 'id'), $contacts)
        ]);
        $free_contacts = $operation->getFreeContacts();
        $linked_contacts = $operation->getLinkedContacts();
        $linked_contacts = array_map(function ($c) {
            if (!empty($c['links'])) {
                $links = [];
                foreach ($c['links'] as $app_id => $_links) {
                    $links[] = [
                        'app'   => $this->getAppName($app_id),
                        'roles' => array_map(function ($l) {
                            return [
                                'role'  => ifset($l, 'role', ''),
                                'count' => (int) $l['links_number']
                            ];
                        }, $_links)
                    ];
                }
                $c['links'] = $links;
            }
            return $c;
        }, $linked_contacts);

        $this->response = [
            'free_contacts'   => array_values($free_contacts),
            'linked_contacts' => array_values($linked_contacts)
        ];
    }

    private function getAppName($app_id)
    {
        static $apps_name = [];

        if (isset($apps_name[$app_id])) {
            return $apps_name[$app_id];
        }
        $app_info = wa()->getAppInfo($app_id);
        $apps_name[$app_id] = ifset($app_info, 'name', '');

        return $apps_name[$app_id];
    }
}
