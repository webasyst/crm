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

    public function updateResponsibleContact($contact_id, $responsible_id = null) {
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

    public function getAvailableResponsibles()
    {
        $sql = "SELECT u.id, u.name, u.photo, COUNT(*) AS `count`
                FROM wa_contact AS c
                    JOIN wa_contact AS u
                        ON c.crm_user_id = u.id
                WHERE c.crm_user_id > 0
                GROUP BY u.id
                ORDER BY u.name ASC";
        $result = $this->query($sql)->fetchAll('id');

        // Result must always contain current user,
        // even if there are no contacts assigned to him.
        $user_id = wa()->getUser()->getId();
        $my_row = array(
            'id'    => wa()->getUser()->getId(),
            'name'  => wa()->getUser()->getName(),
            'photo' => wa()->getUser()->get('photo'),
            'count' => isset($result[$user_id]['count']) ? $result[$user_id]['count'] : 0,
        );

        // Current user must be the first in list.
        return array($user_id => $my_row) + $result;
    }
}