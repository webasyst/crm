<?php

/**
 * Dialog HTML to modify contact of an existing deal,
 * or bind deal to another contact.
 */
class crmDealChangeClientAction extends crmBackendViewAction
{
    public function execute()
    {
        $deal_id = waRequest::post('deal_id', null, waRequest::TYPE_INT);
        $contact_id = waRequest::post('contact_id', null, waRequest::TYPE_STRING);
        $client_type = waRequest::post('client_type', null, waRequest::TYPE_STRING);

        // CONTACT
        $contact = new waContact($contact_id);
        $title = _w("Edit contact");
        if (!$this->getCrmRights()->contact($contact, ['access_to_not_existing' => true])) {
            $this->accessDenied();
        }

        // DEAL
        $dm = new crmDealModel();
        $deal = $dm->getById($deal_id);
        if ($this->getCrmRights()->deal($deal, ['ignore_contact_rights' => true]) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }

        // Participant
        $contact['label'] = crmDeal::getRoleLabel($deal);

        $this->view->assign(array(
            'deal'    => $deal,
            'contact' => $contact,
            'title'   => $title,
            'type'    => $client_type,
            'can_edit_contact' => $this->getCrmRights()->contactEditable($deal['contact_id'])
        ));
    }
}
