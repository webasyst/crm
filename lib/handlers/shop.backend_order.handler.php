<?php

class crmShopBackend_orderHandler extends waEventHandler
{
    public function execute(&$params)
    {
        if (empty($params['id']) || !crmConfig::isShopSupported()) {
            return;
        }

        $single_app_mode_app_id = wa()->isSingleAppMode();
        if (!empty($single_app_mode_app_id) && $single_app_mode_app_id !== 'crm') {
            return;
        }

        $crm_rights = wa()->getUser()->getRights('crm');
        if (empty($crm_rights['backend'])) {
            return;
        }

        $dm = new crmDealModel();
        $deal = $dm->getByField('external_id', 'shop:'.$params['id']);

        if ($deal && $deal['user_contact_id'] > 0) {
            $deal['user'] = $this->newContact($deal['user_contact_id']);
        }

        $can_create_deal = false;
        if ($crm_rights) {
            foreach ($crm_rights as $name => $value) {
                if (($name == 'backend' && $value >= 2) || stripos($name, 'funnel') !== false) {
                    $can_create_deal = true;
                }
            }
        }

        $unread_message_count = (int) $dm->query("SELECT COUNT(*) cnt 
                FROM crm_message m 
                    LEFT JOIN crm_message_read r ON m.id = r.message_id AND r.contact_id = :user_id
                WHERE m.contact_id = :contact_id 
                    AND m.direction = :direction
                    AND m.conversation_id IS NOT NULL 
                    AND r.message_id IS NULL", 
            [
                'user_id' => wa()->getUser()->getId(),
                'contact_id' => $params['contact_id'],
                'direction' => crmMessageModel::DIRECTION_IN,
            ])->fetchField('cnt');

        $view = wa()->getView();
        $view->assign([
            'order_id'        => $params['id'],
            'contact_id'      => $params['contact_id'],
            'deal'            => $deal,
            'can_create_deal' => $can_create_deal,
            'photo'           => empty($params['contact']['photo_50x50']) ? (new waContact($params['contact_id']))->getPhoto() : $params['contact']['photo_50x50'],
            'unread_message_count' => $unread_message_count,
        ]);

        $rights_model = new waContactRightsModel();
        $view->assign('user_no_access_to_list', !$rights_model->get(array(wa()->getUser()->getId()), 'shop', 'orders'));

        $path = wa()->getAppPath('templates/handlers/shop.backend_order.html', 'crm');
        $html = $view->fetch($path);

        return array('action_link' => $html);
    }

    /**
     * Get contact object (even if contact not exists)
     * BUT please don't save it
     *
     * @param int|array $contact ID or data
     * @return waContact
     * @throws waException
     */
    protected function newContact($contact)
    {
        if ($contact instanceof waContact) {
            return $contact;
        }

        $contact_id = 0;
        if (wa_is_int($contact) && $contact > 0) {
            $contact_id = $contact;
        } elseif (isset($contact['id']) && wa_is_int($contact['id']) && $contact['id'] > 0) {
            $contact_id = $contact['id'];
        }

        $wa_contact = new waContact($contact);
        if (!$wa_contact->exists()) {
            $wa_contact = new waContact();
            $wa_contact['id'] = $contact_id;
            $wa_contact['name'] = sprintf_wp("Contact with ID %s doesn't exist", $contact_id);
        }
        return $wa_contact;
    }
}
