<?php

class crmDealParticipantDeleteMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_DELETE;

    public function execute()
    {
        $deal_id = (int) abs($this->get('deal_id', true));
        $contact_id = (int) $this->get('contact_id', true);
        $role = (string) $this->get('role_id',true);
        $force = (bool) $this->post('force');

        $contact = new crmContact($contact_id);
        if (!$contact->exists()) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        } elseif (!in_array($role, [crmDealParticipantsModel::ROLE_CLIENT, crmDealParticipantsModel::ROLE_USER])) {
            throw new waAPIException('unknown_value', 'Unknown value role_id', 400);
        } elseif (!$deal = $this->getDealModel()->getDeal($deal_id, true)) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        }

        $this->http_status_code = 204;
        $this->response = null;
        if ($role === crmDealParticipantsModel::ROLE_USER) {
            $this->deleteUser($deal, $contact, $force);
        } else {
            $this->deleteContact($deal, $contact);
        }
    }

    /**
     * @param $deal
     * @param $contact crmContact
     * @param $force
     * @return void
     * @throws waAPIException
     * @throws waDbException
     * @throws waException
     */
    private function deleteUser($deal, $contact, $force)
    {
        $contact_id = $contact->getId();
        if (!$force) {
            $contact_rights = new crmRights(['contact' => $contact]);
            if ($contact_rights->deal($deal) < crmRightConfig::RIGHT_DEAL_VIEW) {
                throw new waAPIException('forbidden', 'Deal access denied', 403);
            }
            if ($contact_id != $this->getUser()->getId()) {
                $updated_deal = array_merge($deal, [
                    'user_contact_id' => $contact_id
                ]);
                if ($contact_rights->deal($updated_deal) < crmRightConfig::RIGHT_DEAL_VIEW) {
                    $this->http_status_code = 409;
                    $this->response = [
                        'dialog_html' => (new crmDealChangeUserConfirmController())->renderConfirmDialog($deal, $contact_id, '2.0')
                    ];
                }
            }
        }

        if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        } elseif (
            $this->getCrmRights()->funnel($deal['funnel_id']) < 3
            && $deal['user_contact_id'] != $this->getUser()->getId()
        ) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        list($users, $clients) = $this->getUsersAndClients($deal['participants']);
        if (!in_array($contact_id, $users)) {
            return;
        }
        if ($contact_id == $deal['user_contact_id']) {
            $this->getDealModel()->updateById($deal['id'], ['user_contact_id' => 0]);
        }
        $this->getDealParticipantsModel()->deleteByField([
            'contact_id' => $contact_id,
            'deal_id'    => $deal['id'],
            'role_id'    => crmDealParticipantsModel::ROLE_USER
        ]);


        $this->getLogModel()->log(
            'deal_removeowner',
            $deal['id'] * -1,
            $deal['id'],
            $contact->getName(),
            null,
            null,
            ['contact_id' => $contact_id]
        );
    }

    /**
     * @param $deal array
     * @param $contact crmContact
     * @return void
     * @throws waDbException
     * @throws waException
     * @throws waRightsException
     */
    private function deleteContact($deal, $contact)
    {
        if (empty($deal['participants'])) {
            return;
        } elseif ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $funnel_rights_value = $this->getCrmRights()->funnel($deal['funnel_id']);
        if ($contact->getId() == $deal['user_contact_id']) {
            if (
                $deal['user_contact_id'] != $this->getUser()->getId()
                && $funnel_rights_value < 3
                && ($deal['user_contact_id'] || $funnel_rights_value < 1)
            ) {
                throw new waAPIException('forbidden', _w('Access denied'), 403);
            }
        } elseif ($funnel_rights_value < 1) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        list($users, $clients) = $this->getUsersAndClients($deal['participants']);
        if (!in_array($contact->getId(), $clients)) {
            return;
        }

        $this->getDealParticipantsModel()->deleteByField([
            'contact_id' => $contact->getId(),
            'deal_id'    => $deal['id'],
            'role_id'    => crmDealParticipantsModel::ROLE_CLIENT
        ]);
        if ($contact->getId() == $deal['contact_id']) {
            $contact_id = 0;
            if (count($clients) > 1) {
                $contact_id = array_shift($clients);
                if ($contact->getId() == $contact_id) {
                    $contact_id = array_shift($clients);
                }
            }
            $this->getDealModel()->updateById($deal['id'], ['contact_id' => $contact_id]);
        }

        $this->getLogModel()->log(
            'deal_removecontact',
            $deal['id'] * -1,
            $deal['id'],
            $contact->getName(),
            null,
            null,
            ['contact_id' => $contact->getId()]
        );
    }

    /**
     * @param $participants
     * @return array
     */
    private function getUsersAndClients($participants)
    {
        $users = [];
        $clients = [];
        if (!empty($participants)) {
            foreach ($participants as $_participant) {
                if ($_participant['role_id'] === crmDealParticipantsModel::ROLE_USER) {
                    $users[] = $_participant['contact_id'];
                } else {
                    $clients[] = $_participant['contact_id'];
                }
            }
        }

        return [$users, $clients];
    }
}
