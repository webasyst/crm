<?php

class crmContactBlocker
{

    public static function ban($contact, $reason = null)
    {
        if ($contact['is_user'] == 1) {
            return [
                'result' => false,
                'error'  => 'cannot_ban_user',
                'error_description' => _w('This contact is a backend user. Use the Team app to ban them.'),
            ];
        }

        if ($contact['is_user'] == -1) {
            return [
                'result'  => true,
                'code'    => 'already_banned',
                'message' => _w('This contact is already banned.'),
            ];
        }

        (new waContactModel)->updateById($contact['id'], ['is_user' => -1]);

        $log_item_params = empty($reason) ? null : json_encode(['reason' => $reason]);
        (new waLogModel)->add('access_disable', $log_item_params, $contact['id'], wa()->getUser()->getId());
        (new crmLogModel)->add([
            'action'           => 'contact_ban',
            'contact_id'       => $contact['id'],
            'object_id'        => $contact['id'],
            'object_type'      => crmLogModel::OBJECT_TYPE_CONTACT,
            'params'           => $log_item_params,
        ]);

        return [ 'result' => true ];
    }

    public static function unban($contact)
    {
        if (!empty($contact['login'])) {
            return [
                'result' => false,
                'error'  => 'cannot_unban_user',
                'error_description' => _w('This contact was a backend user. Use team app to restore their access.'),
            ];
        }

        if ($contact['is_user'] == 0) {
            return [
                'result'  => true,
                'code'    => 'not_banned',
                'message' => _w('This contact is not banned.'),
            ];
        }

        (new waContactModel)->updateById($contact['id'], ['is_user' => 0]);
        (new waLogModel)->add('access_enable', null, $contact['id'], wa()->getUser()->getId());
        (new crmLogModel)->add([
            'action'           => 'contact_unban',
            'contact_id'       => $contact['id'],
            'object_id'        => $contact['id'],
            'object_type'      => crmLogModel::OBJECT_TYPE_CONTACT,
        ]);

        return [ 'result' => true ];
    }

}
