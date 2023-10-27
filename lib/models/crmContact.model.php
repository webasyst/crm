<?php

class crmContactModel extends waContactModel
{
    public function getAllContacts()
    {
        $contact = new crmContactModel();
        return $contact->select('*')->where('is_user=1')->order('name')->fetchAll('id');
    }

    public function getAllContactsForDeal($deal)
    {
        $contact = new crmContactModel();
        $all_contacts = $contact->select('*')->where('is_user=1')->order('name')->fetchAll('id');
        $contacts = array();
        foreach($all_contacts as $id => $contact) {
            $r = new crmRights(array(
                'contact' => $contact
            ));
            if ($r->deal($deal)) {
                $contacts[$id] = $contact;
            }
        }
        return $contacts;
    }

    /**
     * Смена (назначение) ответственного в переписках
     * при смене (назначении) ответственного контакта
     *
     * @param $contact waContact
     * @param $after_responsible_id
     * @return void
     */
    private function updateResponsibleConversation($contact, $after_responsible_id)
    {
        if (!$after_responsible_id) {
            return;
        }
        $before_responsible_id = (int) $contact->get('crm_user_id');
        $conversation_model = new crmConversationModel();
        if ($before_responsible_id === 0) {
            /** Если у контакта не было ответственного */
            $user_contact_ids = 0;
        } else {
            $user_contact_ids = [0, $before_responsible_id];
        }

        $conversation_model->updateByField([
            'contact_id'      => $contact->getId(),
            'user_contact_id' => $user_contact_ids
        ], [
            'user_contact_id' => $after_responsible_id
        ]);
    }

    public function updateResponsibleContact($contact_id, $responsible_id = null)
    {
        if (!$responsible_id) {
            $responsible_id = null;
        }

        // Get before responsible user (for crm_log);
        try {
            $contact = new waContact($contact_id);
            $before_responsible_id = $contact['crm_user_id'];

            // Before responsible name:
            try {
                $before_responsible = new waContact($before_responsible_id);
                $before_name = $before_responsible['name'];
                if (!$before_name) {
                    $before_name = NULL;
                }
            } catch (Exception $e) {
                $before_name = "deleted contact_id={$before_responsible_id}";
            }
        } catch (Exception $e) {
            $before_name = "";
        }

        // Update responsible user here :x
        $res = false;
        if ($responsible_id != $contact['crm_user_id']) {
            $res = $this->updateById($contact_id, array(
                'crm_user_id' => $responsible_id,
            ));
            $this->updateResponsibleConversation($contact, $responsible_id);
        }
        if ($res) {
            // Add an entry to the crm_log here :|

            // After responsible:
            try {
                $responsible = new waContact($responsible_id);
                $after_name = $responsible['name'];
                if (!$after_name) { // (if used Clear responsibility)
                    $after_name = NULL;
                }
            } catch (Exception $e) {
                $after_name = "deleted contact_id={$responsible_id}";
            }

            $data = array(
                "actor_contact_id" => wa()->getUser()->getId(),
                "action" => "contact_transfer",
                "contact_id" => $contact_id,
                "object_id" => $responsible_id,
                "object_type" => "CONTACT",
                "before" => $before_name,
                "after" => $after_name
            );

            $crmLog = new crmLogModel();
            $crmLog->add($data);
        }

        return $res;
    }

    public function getAvailableResponsibles($scope = 'contact')
    {
        $sql = "SELECT u.id, u.name, u.firstname, u.lastname, u.middlename, u.photo, COUNT(*) AS `count`
                FROM wa_contact AS c
                    JOIN wa_contact AS u
                        ON c.crm_user_id = u.id
                WHERE c.crm_user_id > 0
                GROUP BY u.id
                ORDER BY u.name ASC";
        if ($scope === 'deal') {
            $sql = "SELECT wc.id, wc.name, wc.firstname, wc.lastname, wc.middlename, wc.photo, COUNT(*) AS `count`
                    FROM wa_contact AS wc
                    JOIN crm_deal AS cd ON wc.id = cd.user_contact_id 
                    WHERE cd.user_contact_id > 0
                    GROUP BY wc.id
                    ORDER BY wc.name ASC";
        } elseif ($scope === 'conversation') {
            $sql = "SELECT wc.id, wc.name, wc.firstname, wc.lastname, wc.middlename, wc.photo, COUNT(*) AS `count`
                    FROM wa_contact AS wc
                    JOIN crm_conversation AS cc ON wc.id = cc.user_contact_id
                    WHERE cc.user_contact_id > 0
                    GROUP BY wc.id
                    ORDER BY wc.name ASC";
        }

        $result = $this->query($sql)->fetchAll('id');

        // Result must always contain current user,
        // even if there are no contacts assigned to him.
        /** @var crmContact $user */
        $user = wa()->getUser();
        $user_id = $user->getId();
        $my_row = array(
            'id'         => $user->getId(),
            'name'       => $user->getName(),
            'firstname'  => $user->get('firstname'),
            'lastname'   => $user->get('lastname'),
            'middlename' => $user->get('middlename'),
            'photo'      => $user->get('photo'),
            'count'      => ifset($result, $user_id, 'count', 0),
        );

        // Current user must be the first in list.
        return array($user_id => $my_row) + $result;
    }
}