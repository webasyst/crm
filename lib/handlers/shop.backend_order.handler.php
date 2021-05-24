<?php

class crmShopBackend_orderHandler extends waEventHandler
{
    public function execute(&$params)
    {
        if (empty($params['id']) || !crmConfig::isShopSupported()) {
            return;
        }
        $dm = new crmDealModel();
        $deal = $dm->getByField('external_id', 'shop:'.$params['id']);

        if ($deal && $deal['user_contact_id'] > 0) {
            $deal['user'] = $this->newContact($deal['user_contact_id']);
        }

        $can_create_deal = false;
        $crm_rights = wa()->getUser()->getRights('crm');
        if ($crm_rights) {
            foreach ($crm_rights as $name => $value) {
                if (($name == 'backend' && $value >= 2) || stripos($name, 'funnel') !== false) {
                    $can_create_deal = true;
                }
            }
        }

        $view = wa()->getView();
        $view->assign(array(
            'order_id'        => $params['id'],
            'contact_id'      => $params['contact_id'],
            'deal'            => $deal,
            'can_create_deal' => $can_create_deal,
        ));

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
