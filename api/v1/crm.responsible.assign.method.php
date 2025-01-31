<?php

class crmResponsibleAssignMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $user_id = (int) ifset($_json, 'user_id', 0);
        $contact_ids = (array) ifset($_json, 'contact_id', []);
        $contact_ids = array_map('intval', crmHelper::dropNotPositive($contact_ids));
        $deal_ids = (array) ifset($_json, 'deal_id', []);
        $deal_ids = array_map('intval', crmHelper::dropNotPositive($deal_ids));
        $conversation_ids = (array) ifset($_json, 'conversation_id', []);
        $conversation_ids = array_map('intval', crmHelper::dropNotPositive($conversation_ids));

        if ($user_id < 0) {
            /** Если user_id = 0, то ответственного удаляем */
            throw new waAPIException('invalid_request', _w('Invalid user identifier.'), 400);
        }

        if (empty($contact_ids) && empty($deal_ids) && empty($conversation_ids)) {
            throw new waAPIException(
                'empty_param',
                sprintf_wp(
                    'Missing required parameters: %s.',
                    sprintf(
                        '%s, %s or %s',
                        sprintf_wp('“%s”', 'contact_id'),
                        sprintf_wp('“%s”', 'deal_id'),
                        sprintf_wp('“%s”', 'conversation_id')
                    )
                ),
                400
            );
        }

        if (!empty($contact_ids) + !empty($deal_ids) + !empty($conversation_ids) > 1) {
            throw new waAPIException(
                'error',
                sprintf_wp(
                    'Only one of the parameters can be specified: %s.',
                    sprintf(
                        '%s, %s or %s',
                        sprintf_wp('“%s”', 'contact_id'),
                        sprintf_wp('“%s”', 'deal_id'),
                        sprintf_wp('“%s”', 'conversation_id')
                    )
                ),
                400
            );
        }

        if ($user_id !== 0 && !(new crmContact($user_id))->exists()) {
            throw new waAPIException('invalid_request', _w('Specified user does not exist.'), 400);
        }

        if (!empty($contact_ids)) {
            $this->contactResponsible($contact_ids, $user_id);
        } elseif (!empty($deal_ids)) {
            $this->dealResponsible($deal_ids, $user_id);
        } else {
            $this->conversationResponsible($conversation_ids, $user_id);
        }
    }

    /**
     * @param $contact_ids
     * @param $user_id
     * @return void
     * @throws waAPIException
     * @throws waException
     */
    private function contactResponsible($contact_ids, $user_id)
    {
        $allowed_contact_ids = $this->getCrmRights()->dropUnallowedContacts($contact_ids, 'edit');
        if (empty($allowed_contact_ids)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $unallowed_contacts = array_diff($contact_ids, $allowed_contact_ids);
        $absent_contacts = [];
        $unsuccess_contacts = [];
        $contact_model = new crmContactModel();
        foreach ($allowed_contact_ids as $contact_id) {
            $contact = new crmContact($contact_id);
            if (!$contact->exists()) {
                $absent_contacts[] = $contact_id;
                continue;
            }
            $is_incceptable = $contact->isResponsibleUserIncceptable($user_id);
            if (!$is_incceptable) {
                $contact_model->updateResponsibleContact($contact_id, $user_id);
            } elseif ($is_incceptable == 'no_adhoc_access') {
                $contact->addResponsibleToAdhock($user_id);
                $contact_model->updateResponsibleContact($contact_id, $user_id);
            } elseif ($is_incceptable == 'no_vault_access') {
                $contact = $this->prepareUserpic($contact, self::USERPIC_SIZE);
                $unsuccess_contacts[] = [
                    'id'      => $contact->getId(),
                    'name'    => htmlspecialchars($contact->getName()),
                    'userpic' => rtrim(wa()->getConfig()->getHostUrl(), '/').$contact->getPhoto(self::USERPIC_SIZE)
                ];
            }
        }

        $this->http_status_code = 204;
        $this->response = null;
        $response = [];
        if ($unsuccess_contacts) {
            $message = _w(
                'The responsible user has not been assigned to this client because of insufficient access rights.',
                'The responsible user has not been assigned to these clients because of insufficient access rights.',
                count($unsuccess_contacts)
            );
            $response = [
                'message'            => $message,
                'unsuccess_contacts' => array_values($unsuccess_contacts)
            ];
        }
        if ($absent_contacts) {
            $response['absent_contacts'] = array_values($absent_contacts);
        }
        if ($unallowed_contacts) {
            $response['unallowed_contacts'] = array_values($unallowed_contacts);
        }
        if (!empty($response)) {
            $this->http_status_code = 200;
            $this->response = $response;
        }
    }

    /**
     * @param $deal_ids
     * @param $user_id
     * @return void
     * @throws waAPIException
     * @throws waException
     */
    private function dealResponsible($deal_ids, $user_id)
    {
        $new_user = new crmContact($user_id);

        $allowed_deal_ids = $this->getCrmRights()->dropUnallowedDeals($deal_ids, [ 'level' => crmRightConfig::RIGHT_DEAL_EDIT ]);
        if (empty($allowed_deal_ids)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }
        $unallowed_deals = array_diff($deal_ids, $allowed_deal_ids);
        $deals = $this->getDealModel()->getById($allowed_deal_ids);
        if (!$deals) {
            throw new waAPIException('invalid_request', _w('Invalid deals specified.'), 400);
        }

        $accessable_deals = $deals;
        if ($user_id > 0) {
            $new_user_rights = new crmRights(['contact' => $user_id]);
            $accessable_deals = array_filter($deals, function ($d) use ($new_user_rights) {
                return $new_user_rights->funnel($d['funnel_id']);
            });
        }

        $accessable_deals_ids = array_column($accessable_deals, 'id');
        $unaccessable_deals = array_diff($allowed_deal_ids, $accessable_deals_ids);
        $this->getDealModel()->updateByField(
            ['id' => $accessable_deals_ids],
            ['user_contact_id' => $user_id]
        );

        $participants_model = $this->getDealParticipantsModel();
        $log_model = $this->getLogModel();
        foreach ($accessable_deals as $d) {
            $before_user = new waContact($d['user_contact_id']);
            $participants_model->deleteByField([
                'deal_id'    => $d['id'],
                'contact_id' => $d['user_contact_id'],
                'role_id'    => 'USER',
            ]);
            if ($user_id > 0) {
                $participants_model->replace([
                    'deal_id'    => $d['id'],
                    'contact_id' => $user_id,
                    'role_id'    => 'USER',
                    'label'      => null,
                ]);
                $log_model->log(
                    'deal_transfer',
                    $d['id'] * -1,
                    $d['id'],
                    $before_user->getName(),
                    $new_user->getName(),
                    null,
                    ['user_id_before' => $before_user->getId(), 'user_id_after' => $user_id]
                );
            } else {
                $log_model->log(
                    'deal_removeowner',
                    $d['id'] * -1,
                    $d['id'],
                    $before_user->getName(),
                    null,
                    null,
                    ['user_id_before' => $before_user->getId()]
                );
            }
        }
        $this->http_status_code = 204;
        $this->response = null;
        $response = [];
        if (!empty($unallowed_deals)) {
            $response['unallowed_deals'] = array_values($unallowed_deals);
        }
        if (!empty($unaccessable_deals)) {
            $response['unaccessable_deals'] = array_values($unaccessable_deals);
        }
        if (!empty($response)) {
            $this->http_status_code = 200;
            $this->response = $response;
        }
    }

    /**
     * @param $conversation_ids
     * @param $user_id
     * @return void
     * @throws waAPIException
     * @throws waException
     */
    private function conversationResponsible($conversation_ids, $user_id)
    {
        $conversations = $this->getConversationModel()->getById($conversation_ids);
        if (empty($conversations)) {
            throw new waAPIException('invalid_request', _w('Invalid conversations specified.'), 400);
        }
        $conversation_ids = array_keys($conversations);

        $allowed_conversations = $this->getCrmRights()->dropUnallowedConversations($conversations, ['access_type' => 'edit']);
        if (empty($allowed_conversations)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }
        $allowed_conversation_ids = array_keys($allowed_conversations);
        $unallowed_conversation_ids = array_diff($conversation_ids, $allowed_conversation_ids);

        $after_user = new crmContact($user_id);

        /** Update conversation */
        $data = ['user_contact_id' => ($user_id === 0 ? null : $user_id)];
        $this->getConversationModel()->updateById($allowed_conversation_ids, $data);

        /** Update deal */
        $deals = $this->getDealModel()->getById(array_unique(array_column($allowed_conversations, 'deal_id')));
        if ($user_id !== 0 && $deals) {
            $log_model = $this->getLogModel();
            foreach ($deals as $d) {
                if ($d['user_contact_id'] === $user_id) {
                    continue;
                }
                $before_user = new crmContact($d['user_contact_id']);
                $this->getDealModel()->updateParticipant($d['id'], $user_id, 'user_contact_id');

                $log_model->log(
                    'deal_transfer',
                    $d['id'] * -1,
                    $d['id'],
                    $before_user->getName(),
                    $after_user->getName(),
                    null,
                    ['user_id_before' => $before_user->getId(), 'user_id_after' => $user_id]
                );
            }
        }

        $this->http_status_code = 204;
        $this->response = null;

        if (!empty($unallowed_conversation_ids)) {
            $this->http_status_code = 200;
            $this->response = [
                'unallowed_conversations' => array_values($unallowed_conversation_ids)
            ];
        }
    }
}
