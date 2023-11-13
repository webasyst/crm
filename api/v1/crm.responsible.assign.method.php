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

        if (!$this->getUser()->isAdmin('crm') && !$this->getUser()->getRights('crm', 'edit')) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        } elseif ($user_id < 0) {
            /** Если user_id = 0, то ответственного удаляем */
            throw new waAPIException('not_found', 'User not found', 404);
        } elseif (empty($contact_ids) && empty($deal_ids) && empty($conversation_ids)) {
            throw new waAPIException(
                'empty_param',
                'Required parameter is missing: contact_id, deal_id or conversation_id',
                400
            );
        } elseif (!empty($contact_ids) + !empty($deal_ids) + !empty($conversation_ids) > 1) {
            throw new waAPIException(
                'error',
                'One of the parameters is required: contact_id, deal_id or conversation_id',
                400
            );
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
        if ($user_id !== 0 && !(new crmContact($user_id))->exists()) {
            throw new waAPIException('not_found', 'User not found', 404);
        }

        $absent_contacts = [];
        $unsuccess_contacts = [];
        $contact_model = new crmContactModel();
        foreach ($contact_ids as $contact_id) {
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
        if ($unsuccess_contacts) {
            $message = _w(
                'The responsible person is not assigned to this client, since he has no access rights.',
                'The responsible person is not assigned to these clients, since he has no access rights.',
                count($unsuccess_contacts)
            );
            $this->http_status_code = 200;
            $this->response = [
                'message'            => $message,
                'unsuccess_contacts' => $unsuccess_contacts
            ];
        }
        if ($absent_contacts) {
            $this->http_status_code = 200;
            $this->response['absent_contacts'] = $absent_contacts;
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
        $user = new crmContact($user_id);
        if (!$user->exists()) {
            throw new waAPIException('not_found', 'User not found', 404);
        }
        $deals = $this->getDealModel()->getById($deal_ids);

        if (!$deals) {
            throw new waAPIException('not_found', 'Deals not found', 404);
        }

        $_deal = reset($deals);
        if (!$this->getCrmRights()->funnel($_deal['funnel_id'])) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }
        $this->getDealModel()->updateByField(
            ['id' => $deal_ids],
            ['user_contact_id' => $user_id]
        );

        $participants_model = $this->getDealParticipantsModel();
        $log_model = $this->getLogModel();
        foreach ($deals as $d) {
            $before_user = new waContact($d['user_contact_id']);
            $participants_model->deleteByField([
                'deal_id'    => $d['id'],
                'contact_id' => $d['user_contact_id'],
                'role_id'    => 'USER',
            ]);
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
                $user->getName(),
                null,
                ['user_id_before' => $before_user->getId(), 'user_id_after' => $user_id]
            );
        }
        $this->http_status_code = 204;
        $this->response = null;
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
            throw new waAPIException('not_found', 'Conversations not found', 404);
        }

        $allowed_conversations = $this->getCrmRights()->dropUnallowedConversations($conversations, ['access_type' => 'edit']);
        $conversation_ids = array_keys($conversations);
        $can_edit_conversation_diff = array_diff($conversation_ids, array_keys($allowed_conversations));
        if (!empty($can_edit_conversation_diff)) {
            throw new waAPIException(
                'forbidden', '
                Access denied conversation_id: '.implode(', ', $can_edit_conversation_diff),
                403
            );
        }

        $after_user = new crmContact($user_id);
        if ($user_id !== 0 && !$after_user->exists()) {
            throw new waAPIException('not_found', 'User not found', 404);
        }

        /** Update conversation */
        $data = ['user_contact_id' => ($user_id === 0 ? null : $user_id)];
        $this->getConversationModel()->updateById($conversation_ids, $data);

        /** Update deal */
        $deals = $this->getDealModel()->getById(array_unique(array_column($conversations, 'deal_id')));
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
    }
}
