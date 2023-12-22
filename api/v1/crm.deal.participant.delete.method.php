<?php

class crmDealParticipantDeleteMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_DELETE;

    public function execute()
    {
        $deal_id = (int) $this->get('deal_id', true);
        $contact_id = (int) $this->get('contact_id', true);
        $role = (string) $this->get('role_id',true);
        $force = (bool) $this->post('force');

        if (!in_array($role, [crmDealParticipantsModel::ROLE_CLIENT, crmDealParticipantsModel::ROLE_USER])) {
            throw new waAPIException('unknown_value', sprintf_wp('Unknown “%s” value.', 'role_id'), 400);
        }
        if (!$deal = $this->getDealModel()->getDeal($deal_id, true)) {
            throw new waAPIException('not_found', _w('Deal not found'), 404);
        }
        if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $this->http_status_code = 204;
        $this->response = null;
        if ($role === crmDealParticipantsModel::ROLE_USER) {
            $this->deleteUser($deal, $contact_id, $force);
        } else {
            $this->deleteContact($deal, $contact_id);
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
    private function deleteUser($deal, $contact_id, $force)
    {
        list($users, $clients) = $this->getUsersAndClients($deal['participants']);
        if (!in_array($contact_id, $users) && $contact_id != $deal['user_contact_id']) {
            return;
        }
        if (!$force) {
            if ($contact_id == $this->getUser()->getId()) {
                $updated_deal = $deal;
                $updated_deal['user_contact_id'] = 0;
                $updated_deal['participants'] = array_filter($deal['participants'], function ($_participant) use ($contact_id) {
                    return $_participant['contact_id'] != $contact_id;
                });
                if ($this->getCrmRights()->deal($updated_deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
                    $this->http_status_code = 409;
                    $this->response = [
                        'dialog_html' => (new crmDealChangeUserConfirmController())->renderConfirmDialog($deal, $contact_id, '2.0')
                    ];
                }
            }
        }

        $action = 'deal_removeuser';
        if ($contact_id == $deal['user_contact_id']) {
            $this->getDealModel()->updateById($deal['id'], ['user_contact_id' => 0]);
            $action = 'deal_removeowner';
        }
        $this->getDealParticipantsModel()->deleteByField([
            'contact_id' => $contact_id,
            'deal_id'    => $deal['id'],
            'role_id'    => crmDealParticipantsModel::ROLE_USER
        ]);

        $contact = new crmContact($contact_id);
        $contact_name = $contact->exists() ? $contact->getName() : _w('Was deleted');
        $this->getLogModel()->log(
            $action,
            $deal['id'] * -1,
            $deal['id'],
            $contact_name,
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
    private function deleteContact($deal, $contact_id)
    {
        list($users, $clients) = $this->getUsersAndClients($deal['participants']);
        if (!in_array($contact_id, $clients) && $contact_id != $deal['contact_id']) {
            return;
        }

        $this->getDealParticipantsModel()->deleteByField([
            'contact_id' => $contact_id,
            'deal_id'    => $deal['id'],
            'role_id'    => crmDealParticipantsModel::ROLE_CLIENT
        ]);
        if ($contact_id == $deal['contact_id']) {
            $clients = array_filter($clients, function ($_participant) use ($contact_id) {
                return $_participant != $contact_id;
            });

            $new_contact_id = count($clients) > 0 ? array_shift($clients) : 0;
            $this->getDealModel()->updateById($deal['id'], ['contact_id' => $new_contact_id]);
        }

        $contact = new crmContact($contact_id);
        $contact_name = $contact->exists() ? $contact->getName() : _w('Was deleted');
        $this->getLogModel()->log(
            'deal_removecontact',
            $deal['id'] * -1,
            $deal['id'],
            $contact_name,
            null,
            null,
            ['contact_id' => $contact_id]
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
