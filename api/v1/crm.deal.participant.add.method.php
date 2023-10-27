<?php

class crmDealParticipantAddMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $userpic_size = (int) $this->get('userpic_size');
        $userpic_size = ifempty($userpic_size, self::USERPIC_SIZE);
        $deal_id = (int) abs(ifset($_json, 'deal_id', null));
        $contact_id = (int) ifset($_json, 'contact_id', null);
        $role = (string) ifset($_json,'role_id','');
        $label = (string) ifset($_json, 'label', '');
        $is_responsible = (bool) ifset($_json, 'is_responsible', false);
        $force = (bool) ifset($_json, 'force', false);
        $replace_contact_id = (int) ifset($_json, 'replace_contact_id', null);

        if (empty($deal_id) || empty($contact_id) || empty($role)) {
            throw new waAPIException('required_param', 'Required parameters is missing: deal_id, contact_id, role_id', 400);
        } elseif ($contact_id < 1) {
            throw new waAPIException('not_found', 'Contact not found', 404);
        } elseif (!in_array($role, [crmDealParticipantsModel::ROLE_CLIENT, crmDealParticipantsModel::ROLE_USER])) {
            throw new waAPIException('unknown_value', 'Unknown value role_id', 400);
        } elseif (!$deal = $this->getDealModel()->getDeal($deal_id, true)) {
            throw new waAPIException('not_found', 'Deal not found', 404);
        }

        $contact = new crmContact($contact_id);
        if (!$contact->exists()) {
            throw new waAPIException('not_found', 'Contact not found', 404);
        }

        $deal_access_level = $this->getCrmRights()->deal($deal);
        if ($deal_access_level <= crmRightConfig::RIGHT_DEAL_VIEW) {
            throw new waAPIException('forbidden', 'Access denied', 403);
        }

        $participant_exists = false;
        if ($this->getDealModel()->isRelated($deal_id, $contact_id, $role)) {
            $participant_exists = true;
        }

        $this->http_status_code = 201;
        $this->replaceContact($deal, $replace_contact_id, $role);
        if ($role === crmDealParticipantsModel::ROLE_CLIENT) {
            $this->response = $this->dealAddParticipantClient(
                $deal,
                $contact,
                $label,
                $replace_contact_id,
                $participant_exists,
                $userpic_size
            );
        } elseif ($role === crmDealParticipantsModel::ROLE_USER) {
            $this->response = $this->dealAddParticipantUser(
                $deal,
                $contact,
                $label,
                $replace_contact_id,
                $is_responsible,
                $force,
                $participant_exists,
                $userpic_size
            );
        }
    }

    /**
     * @param $deal
     * @param $contact crmContact
     * @param $label
     * @param $replace_contact_id
     * @param $participant_exists bool
     * @param $userpic_size
     * @return array
     * @throws waAPIException
     */
    private function dealAddParticipantClient($deal, $contact, $label, $replace_contact_id, $participant_exists, $userpic_size)
    {
        $contact_id = $contact->getId();
        if (!empty($deal['participants'])) {
            $client_count = 0;
            foreach ($deal['participants'] as $_participant) {
                if (
                    $_participant['role_id'] === crmDealParticipantsModel::ROLE_CLIENT
                    && $_participant['contact_id'] != $replace_contact_id
                ) {
                    $client_count++;
                }
            }
            if ($client_count === 0) {
                $this->getDealModel()->updateById($deal['id'], ['contact_id' => $contact->getId()]);
            }
        }

        $participant = [
            'contact_id' => $contact_id,
        ];

        if ($participant_exists) {
            $this->getDealParticipantsModel()->updateByField([
                'deal_id'    => $deal['id'],
                'contact_id' => $contact_id,
                'role_id'    => crmDealParticipantsModel::ROLE_CLIENT
            ], [
                'label' => $label
            ]);
        } elseif ($this->getDealModel()->addParticipants($deal, [$participant], crmDealParticipantsModel::ROLE_CLIENT, $label)) {
            $this->getLogModel()->log(
                ($replace_contact_id ? 'deal_contact_change' : 'deal_addcontact'),
                $deal['id'] * -1,
                $deal['id'],
                ($replace_contact_id ? (new crmContact($replace_contact_id))->getName() : null),
                $contact->getName(),
                null,
                ['contact_id' => $participant['contact_id']]
            );
        } else {
            throw new waAPIException('not_added', 'Participant not added', 400);
        }

        return $this->getDealContact($contact, $deal, $label, $userpic_size);
    }

    /**
     * @param $deal array
     * @param $contact crmContact
     * @param $label string
     * @param $replace_contact_id
     * @param $is_responsible bool
     * @param $force bool
     * @param $participant_exists bool
     * @param $userpic_size int
     * @return array
     * @throws waAPIException
     * @throws waDbException
     * @throws waException
     */
    private function dealAddParticipantUser($deal, $contact, $label, $replace_contact_id, $is_responsible, $force, $participant_exists, $userpic_size)
    {
        $result = [];
        $contact_id = $contact->getId();
        $contact_rights = new crmRights(['contact' => $contact]);
        if (!$force && $result = $this->hasAccessToDeal($deal, $contact, $contact_rights)) {
            return $result;
        }
        if ($replace_contact_id && $result = $this->hasAccessToDeal($deal, $replace_contact_id)) {
            return $result;
        }

        if (!$contact_rights->funnel($deal['funnel_id'])) {
            throw new waAPIException('forbidden', 'Funnel access denied', 403);
        }

        if ($is_responsible) {
            $funnel_rights = $this->getCrmRights()->funnel($deal['funnel_id']);
            if (
                $funnel_rights < crmRightConfig::RIGHT_FUNNEL_ALL
                && $deal['user_contact_id'] != wa()->getUser()->getId()
                && ($deal['user_contact_id'] || $funnel_rights < crmRightConfig::RIGHT_FUNNEL_OWN)
            ) {
                throw new waAPIException('forbidden', 'Funnel access denied', 403);
            }

            if ($deal['user_contact_id'] != $contact_id) {
                $before_user = new waContact($deal['user_contact_id']);
                $before_user_name = '';
                if ($deal['user_contact_id'] > 0) {
                    $before_user_name = "deleted contact_id={$deal['user_contact_id']}";
                    if ($before_user->exists()) {
                        $before_user_name = $before_user->getName();
                    }
                }

                $this->getDealModel()->updateParticipant($deal['id'], $contact_id, 'user_contact_id', $label);
                $this->getLogModel()->log(
                    'deal_transfer',
                    $deal['id'] * -1,
                    $deal['id'],
                    $before_user_name,
                    $contact->getName(),
                    null,
                    ['user_id_before' => $before_user->getId(), 'user_id_after' => $contact_id]
                );
                $deal['user_contact_id'] = $contact_id;

                $this->getConversationModel()->updateByField(
                    ['deal_id' => $deal['id'], 'is_closed' => 0],
                    ['user_contact_id' => $contact_id]
                );
            } else {
                // update label
                $this->getDealModel()->updateParticipant($deal['id'], $contact_id, 'user_contact_id', $label);
            }

            $deal_access_level = $this->getCrmRights()->deal($deal);
            $result = [
                'user_contact_id' => $contact_id,
                'rights' => [
                    'has_access_to_funnel' => $funnel_rights > crmRightConfig::RIGHT_FUNNEL_NONE,
                    'can_edit' => ($deal_access_level > crmRightConfig::RIGHT_DEAL_VIEW),
                    'can_delete' => ($deal_access_level === crmRightConfig::RIGHT_DEAL_ALL),
                    'can_manage_responsible' => ($contact_id == $this->getUser()->getId()
                        || $funnel_rights > 2
                        || !$deal['contacts']['user'] && $funnel_rights > 0)
                ]
            ];
        } else {
            if ($participant_exists) {
                $this->getDealParticipantsModel()->updateByField([
                    'deal_id'    => $deal['id'],
                    'contact_id' => $contact_id,
                    'role_id'    => crmDealParticipantsModel::ROLE_USER
                ], [
                    'label' => $label
                ]);
                if (!$is_responsible) {
                    $this->getDealModel()->updateById($deal['id'], ['user_contact_id' => 0]);
                }
            } else {
                $this->getDealParticipantsModel()->insert([
                    'contact_id' => $contact_id,
                    'deal_id'    => $deal['id'],
                    'role'       => crmDealParticipantsModel::ROLE_USER,
                    'label'      => $label
                ]);
                $this->getLogModel()->log(
                    ($replace_contact_id ? 'deal_contact_change' : 'deal_addcontact'),
                    $deal['id'] * -1,
                    $deal['id'],
                    ($replace_contact_id ? (new crmContact($replace_contact_id))->getName() : null),
                    $contact->getName(),
                    null,
                    ['contact_id' => $contact_id]
                );
            }
        }

        return [
            'user' => [
                'label' => $label,
                'assigned_at' => $this->formatDatetimeToISO8601($contact['create_datetime']),
                'contact' => [
                    'id' => $contact_id,
                    'name' => $contact->getName(),
                    'userpic' => $this->getDataResourceUrl($contact->getPhoto2x($userpic_size))
                ]
            ]
        ] + $result;
    }

    private function replaceContact($deal, $replace_contact_id, $role)
    {
        if ($replace_contact_id > 0) {
            $this->getDealParticipantsModel()->deleteByField([
                'deal_id'    => $deal['id'],
                'contact_id' => $replace_contact_id,
                'role_id'    => $role
            ]);
        }
    }

    /**
     * @param $contact crmContact
     * @param $deal
     * @param $label
     * @param $userpic_size
     * @return array[]
     * @throws waException
     */
    private function getDealContact($contact, $deal, $label, $userpic_size)
    {
        $address_obj = waContactFields::get('address');
        $has_order = (crmShop::appExists() && !!crmShop::getOrderByDeal($deal));
        $counters = crmDeal::getDealPageContactCounters($contact, [$contact], $has_order);
        $counter_deal = ifset($counters, 'deal_counters', []);
        $counter_shop = ifset($counters, 'order_counters', []);
        $contact = $this->prepareUserpic($contact, $userpic_size);

        $_contact = [
            'phone' => $this->addFormattedPhoneValues($contact['phone']),
            'email' => $this->addFormattedEmailValues($contact['email'])
        ];
        if (!empty($contact['address'])) {
            $_contact['address'] = array_map(function ($_address) use ($address_obj) {
                $addr_short = $address_obj->format($_address, 'short');
                return [
                    'value'   => ifempty($addr_short, 'value', ''),
                    'map_url' => $this->getUrlMap(
                        $address_obj->format($_address, 'value'),
                        ifset($_address, 'data', 'lng', null),
                        ifset($_address, 'data', 'lat', null)
                    )
                ];
            }, $contact['address']);
        }

        foreach (['id', 'name', 'userpic', 'jobtitle', 'company', 'company_contact_id'] as $field) {
            if ($contact[$field]) {
                $_contact[$field] = ($field === 'company_contact_id' ? (int) $contact[$field] : $contact[$field]);
            }
        }
        if (!!ifempty($contact, 'is_company', '')) {
            $_contact['company'] = '';
        }

        return [
            'contact' => [
                'label'       => $label,
                'assigned_at' => $this->formatDatetimeToISO8601($contact['assigned_at']),
                'contact'     => $_contact,
                'counters'    => [
                    'deal'       => ifset($counter_deal, $contact->getId(), null),
                    'shop_order' => ifset($counter_shop, $contact->getId(), null)
                ]
            ]
        ];
    }

    /**
     * @param $deal array
     * @param $contact crmContact|int
     * @param $contact_rights crmRights|null
     * @return array
     * @throws waAPIException
     * @throws waDbException
     * @throws waException
     */
    private function hasAccessToDeal($deal, $contact, $contact_rights = null)
    {
        if (!($contact instanceof crmContact)) {
            $contact = new crmContact($contact);
        }
        if (!($contact_rights instanceof crmRights)) {
            $contact_rights = new crmRights(['contact' => $contact]);
        }
        if ($contact_rights->deal($deal) < crmRightConfig::RIGHT_DEAL_VIEW) {
            throw new waAPIException('forbidden', 'Deal access denied', 403);
        }
        if ($contact->getId() != $this->getUser()->getId()) {
            $updated_deal = array_merge($deal, [
                'user_contact_id' => $contact->getId()
            ]);
            if ($contact_rights->deal($updated_deal) < crmRightConfig::RIGHT_DEAL_VIEW) {
                $this->http_status_code = 409;
                return [
                    'dialog_html' => (new crmDealChangeUserConfirmController())->renderConfirmDialog($deal, $contact->getId(), '2.0')
                ];
            }
        }

        return [];
    }
}
