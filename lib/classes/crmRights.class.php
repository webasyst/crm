<?php

class crmRights
{
    /**
     * @var array
     */
    protected $options;

    /**
     * @var waContact
     */
    protected $contact;

    /**
     * @var string
     */
    protected $app_id = 'crm';

    /**
     * @var bool
     */
    protected $is_admin = false;

    public function __construct($options = array())
    {
        $this->options = $options;
        $contact = ifset($options['contact']);
        if (wa_is_int($contact) && $contact > 0) {
            $this->contact = new waContact($contact);
        } else {
            if ($contact instanceof waContact) {
                $this->contact = $contact;
            } else {
                $this->contact = wa()->getUser();
            }
        }
        $this->is_admin = $this->contact->isAdmin($this->app_id);
    }

    public function canArchiveSegment($segment)
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->canEditSegment($segment);
    }

    public function canEditSegment($segment)
    {
        $segment = (array)$segment;

        // need this key for check rights
        if (!array_key_exists('system_id', $segment)) {
            return false;
        }

        // it's system category
        if (!empty($segment['system_id'])) {
            return false;
        }

        $segment['contact_id'] = (int) ifset($segment['contact_id']);
        $segment['shared'] = (int) ifset($segment['shared']);

        $contact_id = $this->contact->getId();
        $is_creator = $segment['contact_id'] == $contact_id;
        $is_shared = $segment['shared'];
        $is_personal = !$is_shared;

        // if it's personal segment, but not of user, user can't edit it, even if user's admin
        if ($is_personal && !$is_creator) {
            return false;
        }

        // admin can edit all rest kind of segments, but other users - only created by own self
        return $this->is_admin || $is_creator;
    }

    public function dropUnallowedToEditSegments($segments)
    {
        foreach ($segments as $id => $segment) {
            if (!$this->canEditSegment($segment)) {
                unset($segments[$id]);
            }
        }
        return $segments;
    }

    /**
     * @param $funnel
     * @return bool|int - crmRightsConfig::RIGHT_FUNNEL_*
     * @see crmRightConfig
     */
    public function funnel($funnel)
    {
        if (wa_is_int($funnel)) {
            $funnel_id = $funnel;
        } else {
            $funnel_id = ifset($funnel['id']);
        }
        if ($funnel_id > 0) {
            return $this->contact->getRights('crm', 'funnel.' . $funnel_id);
        } else {
            return $this->isAdmin();
        }
    }

    /**
     * Get groups that has at least limited access to crm
     * @return array
     * @throws waDbException
     * @throws waException
     */
    public function getAvailableGroupsForCrm()
    {
        $crm = new waContactRightsModel();

        $is_super_admin = "(`app_id` = 'webasyst' AND `name` = 'backend' AND `value` > 0)";
        $is_crm_admin = "(`app_id` = 'crm' AND `name` = 'backend' AND `value` >= 1)";

        $sql = "SELECT DISTINCT `group_id`
                FROM  `wa_contact_rights`
                WHERE `group_id` > 0 AND ({$is_super_admin} OR {$is_crm_admin})";

        $group_ids = $crm->query($sql)->fetchAll(null, true);
        if (!$group_ids) {
            return array();
        }
        $gm = new waGroupModel();
        return $gm->getById($group_ids);
    }

    public function getAvailableGroupsForFunnel($funnel)
    {
        $funnel_id = is_array($funnel) ? ifset($funnel['id']) : $funnel;
        $funnel_id = (int)$funnel_id;
        if ($funnel_id <= 0) {
            return array();
        }

        $crm = new waContactRightsModel();

        $is_super_admin = "(`app_id` = 'webasyst' AND `name` = 'backend' AND `value` > 0)";
        $is_crm_admin = "(`app_id` = 'crm' AND `name` = 'backend' AND `value` > 1)";
        $has_access_to_funnel = "(`app_id` = 'crm' AND `name` = 'funnel.{$funnel_id}' AND `value` > 0)";

        $sql = "SELECT DISTINCT `group_id`
                FROM  `wa_contact_rights`
                WHERE `group_id` > 0 AND (
                    {$is_super_admin} OR
                    {$is_crm_admin} OR
                    {$has_access_to_funnel}
                )";

        $group_ids = $crm->query($sql)->fetchAll(null, true);
        if (!$group_ids) {
            return array();
        }
        $gm = new waGroupModel();
        return $gm->getById($group_ids);
    }

    public function contactOrDeal($contact_id)
    {
        if (!$contact_id) {
            return true;
        }
        if ($contact_id > 0) {
            return $this->contact($contact_id);
        } else {
            return $this->deal(-$contact_id);
        }
    }

    /**
     * @param waContact|int|array $contact
     * @param array $options
     *      bool $options['access_to_not_existing'] [optional] - Has access to not existing contact. Default is FALSE.
     * @return bool
     * @throws waDbException
     * @throws waException
     */
    public function contact($contact, $options = [])
    {
        $options = is_array($options) ? $options : [];

        $access_to_not_existing = !empty($options['access_to_not_existing']);

        if ($access_to_not_existing) {
            $exists = false;
            if (is_array($contact) && isset($contact['id'])) {
                $contact = $contact['id'];
            }
            if (wa_is_int($contact) && wa_is_int($contact) > 0) {
                $contact = new waContact($contact);
            }
            if ($contact instanceof waContact && $contact->exists()) {
                $exists = $contact->exists();
            }
            if (!$exists) {
                return true;
            }
        }

        $res = $this->dropUnallowedContacts(array($contact));
        return !empty($res);
    }

    /**
     * @param $contacts
     * @param $type string 'view' or 'edit'
     *
     * @return array waContact[]|int[]|array[]
     * If contact item is associative array it MUST HAS that required fields: 'id', 'crm_vault_id', 'crm_user_id', 'create_contact_id'
     *
     * @throws waDbException
     * @throws waException
     */
    public function dropUnallowedContacts($contacts, $type = 'view')
    {
        if (empty($contacts)) {
            return array();
        }

        if ($this->isAdmin()) {
            return $contacts;
        }

        // define type of item in array
        $contact = reset($contacts);
        if (wa_is_int($contact)) {
            $contact_type = 'int';
        } else if ($contact instanceof waContact) {
            $contact_type = 'wa_contact';
        } else if (is_array($contact)) {
            $contact_type = 'array';
        } else {
            $contact_type = null;
        }

        // unknown item type
        if (!$contact_type) {
            return array();
        }

        // drop type incompatibility items
        foreach ($contacts as $idx => $contact) {
            if (wa_is_int($contact)) {
                $item_type = 'int';
            } else if ($contact instanceof waContact) {
                $item_type = 'wa_contact';
            } else if (is_array($contact)) {
                $item_type = 'array';
            } else {
                $item_type = null;
            }
            if ($item_type != $contact_type) {
                unset($contacts[$idx]);
            }
        }

        if (empty($contacts)) {
            return array();
        }

        if ($contact_type == 'int') {
            $contact_model = new crmContactModel();
            $contacts = $contact_model
                ->select('id, crm_vault_id, crm_user_id, create_contact_id')
                ->where('id IN (:ids)', array('ids' => $contacts))
                ->fetchAll('id');
        }

        $available_vault_ids = array_fill_keys($this->getAvailableVaultIds(), true);

        // Can edit all visible contacts if access rights allow
        if ($type == 'edit' && $this->contact->getRights('crm', 'edit')) {
            $type = 'view';
        }

        foreach ($contacts as $idx => $contact) {

            $vault_id = isset($contact['crm_vault_id']) ? (int)$contact['crm_vault_id'] : null;
            if (empty($available_vault_ids[$vault_id])) {
                unset($contacts[$idx]);
                continue;
            }
            if ($type == 'edit') {
                // Owner of a contact can edit
                $can_edit = $vault_id < 0;

                // Creator of a contact can edit
                $create_contact_id = isset($contact['create_contact_id']) ? (int)$contact['create_contact_id'] : null;
                $can_edit = $can_edit || $create_contact_id == $this->contact->getId();

                // Responsible of a contact can edit
                $responsible_contact_id = isset($contact['crm_user_id']) ? (int)$contact['crm_user_id'] : null;
                $can_edit = $can_edit || $responsible_contact_id == $this->contact->getId();

                if (!$can_edit) {
                    unset($contacts[$idx]);
                }
            }
        }

        if ($contact_type === 'int') {
            $contacts = array_keys($contacts);
        }

        return $contacts;
    }

    /**
     * @param waContact|int|array $contact
     * @param array $options
     *      bool $options['access_to_not_existing'] [optional] - Has access to not existing contact. Default is FALSE.
     * @return bool
     * @throws waDbException
     * @throws waException
     */
    public function contactEditable($contact, $options = [])
    {
        $options = is_array($options) ? $options : [];

        $access_to_not_existing = !empty($options['access_to_not_existing']);

        if ($access_to_not_existing) {
            $exists = false;
            if (is_array($contact) && isset($contact['id'])) {
                $contact = $contact['id'];
            }
            if (wa_is_int($contact) && wa_is_int($contact) > 0) {
                $contact = new waContact($contact);
            }
            if ($contact instanceof waContact && $contact->exists()) {
                $exists = $contact->exists();
            }
            if (!$exists) {
                return true;
            }
        }

        $res = $this->dropUnallowedContacts(array($contact), 'edit');
        return !empty($res);
    }

    public function contactVaultId($contact_vault_id)
    {
        if (!$contact_vault_id || $this->isAdmin()) {
            return true;
        }

        if ($contact_vault_id > 0) {
            return !!$this->contact->getRights('crm', 'vault.'.$contact_vault_id);
        }

        $adhoc_group_model = new crmAdhocGroupModel();
        return !!$adhoc_group_model->getByField(array(
            'contact_id' => $this->contact->getId(),
            'adhoc_id'   => -$contact_vault_id,
        ));
    }

    function classifyContactAccess($contact)
    {
        // Admin can do anything
        if ($this->isAdmin()) {
            return true;
        }
        // Can edit all visible contacts if access rights allow
        if ($this->contact->getRights('crm', 'edit') && $this->contact($contact)) {
            return true;
        }
        // No one except admin can classify contacts belonding to vaults
        if ($contact['crm_vault_id'] > 0) {
            return false;
        }
        // Free contact can be classified by user who created them
        if ($contact['crm_vault_id'] == 0) {
            return $contact['create_contact_id'] == $this->contact->getId();
        }
        // Contacts assigned to specific users can be classified by those users
        return $this->contactVaultId($contact['crm_vault_id']);
    }

    /**
     * Admin to crm
     * @return bool
     */
    public function isAdmin()
    {
        return $this->is_admin;
    }

    /**
     * @param $deal
     * @param array $options
     *      - bool $options['ignore_contact_rights'] - ignore checking contact rights or not, default is FALSE (not ignore)
     *      - bool $options['access_to_not_existing_contact'] - Has access to not existing contact, will pass to contact() method, default is TRUE
     * @return int crmRightConfig::RIGHT_DEAL_* const
     * @see contact
     * @throws waDbException
     * @throws waException
     * @see crmRightConfig
     */
    public function deal($deal, $options = [])
    {
        // backward compatibility
        if (is_bool($options)) {
            $options = [
                'ignore_contact_rights' => $options
            ];
        }

        $options = is_array($options) ? $options : [];
        $ignore_contact_rights = !empty($options['ignore_contact_rights']);

        $access_to_not_existing_contact = true;
        if (array_key_exists('access_to_not_existing_contact', $options)) {
            $access_to_not_existing_contact = !empty($options['access_to_not_existing_contact']);
        }

        // Admin can do anything.
        if ($this->contact->isAdmin($this->app_id)) {
            return crmRightConfig::RIGHT_DEAL_ALL;
        }

        if (!is_array($deal)) {
            $dm = new crmDealModel();
            $deal = $dm->getById($deal);
        }

        if (!is_array($deal) || empty($deal)) {
            return crmRightConfig::RIGHT_DEAL_NONE;
        }

        // Check access by funnel
        $funnel_rights = $this->funnel($deal['funnel_id']);
        if (!$funnel_rights) {

            // Allow view deal if contact is user participant regardless no access to funnel
            $is_deal_user_participant = $this->isDealUserParticipant($deal);
            if ($is_deal_user_participant) {
                return crmRightConfig::RIGHT_DEAL_VIEW;
            } else {
                return crmRightConfig::RIGHT_DEAL_NONE;
            }
        }

        // Make sure main deal contact is visible to user
        if (!$ignore_contact_rights && !$this->contact($deal['contact_id'], ['access_to_not_existing' => $access_to_not_existing_contact])) {
            return crmRightConfig::RIGHT_DEAL_NONE;
        }

        // Allow if all deals in funnel are allowed
        if ($funnel_rights >= crmRightConfig::RIGHT_FUNNEL_ALL) {
            return crmRightConfig::RIGHT_DEAL_ALL;
        }

        // Allow for unassigned users.
        if ($funnel_rights == crmRightConfig::RIGHT_FUNNEL_OWN_UNASSIGNED) {
            if (!$deal['user_contact_id']) {
                return crmRightConfig::RIGHT_DEAL_EDIT;
            } else {
                $user_contact = new waContact($deal['user_contact_id']);
                if (!$user_contact->exists()) {
                    return crmRightConfig::RIGHT_DEAL_EDIT;
                }
            }
        }


        // Allow for user assigned to deal.
        if ($deal['user_contact_id'] == $this->contact->getId()) {
            return crmRightConfig::RIGHT_DEAL_EDIT;
        }

        // Allow for participant user
        $is_deal_user_participant = $this->isDealUserParticipant($deal);
        if ($is_deal_user_participant) {
            return crmRightConfig::RIGHT_DEAL_EDIT;
        }

        // Nope... not allowed.
        return crmRightConfig::RIGHT_DEAL_NONE;
    }

    /**
     * Drop from input deals array that deals that is unallowed to access for current contact
     * @param int[]|array $deals - list of deals from DB OR list of deal IDs
     * @param array $options
     *      - bool $options['ignore_contact_rights'] [optional] - default is FALSE
     *      - int  $options['level'] [optional] - which level is minimal allowed. See crmRightConfig::RIGHT_DEAL_*.
     *              Default is crmRightConfig::RIGHT_DEAL_VIEW, what means that all deals with level < crmRightConfig::RIGHT_DEAL_VIEW is not allowed
     *
     * @return array|int[] - same type array as in input just filtered by access level
     * @throws waDbException
     * @throws waException
     * @see crmRightConfig
     */
    public function dropUnallowedDeals($deals, $options = [])
    {
        if ($this->isAdmin()) {
            return $deals;
        }

        $deal_type = $this->getInputArrayType($deals);

        if (!$deal_type) {
            return [];
        }

        $options = is_array($options) ? $options : [];

        $min_allowed_level = array_key_exists('level', $options) ? $options['level'] : crmRightConfig::RIGHT_DEAL_VIEW;

        $deal_ids = [];
        if ($deal_type === 'array') {
            foreach ($deals as $idx => $deal) {
                $deal_ids[$idx] = $deal['id'];
            }
        } else {
            $deal_ids = $deals;
        }

        // index to deal id map, to define which item (by index) need to unset from input array
        $idx_deal_id = array_flip($deal_ids);

        $levels = $this->deals($deals, $options);

        foreach ($levels as $deal_id => $level) {
            // just in case
            if (!isset($idx_deal_id[$deal_id])) {
                continue;
            }

            $idx = $idx_deal_id[$deal_id];
            if ($level < $min_allowed_level) {
                unset($deals[$idx]);
            }
        }

        return $deals;
    }

    /**
     * Get deal(s) access level(s) for current contact
     * @param int[]|array $deals - list of deals from DB OR list of deal IDs
     * @param array $options
     *      - bool $options['ignore_contact_rights'] [optional] - default is FALSE
     * @return array<deal_id, int>|int - map from deal ID to access level (or one access level if input is for one deal). See crmRightConfig::RIGHT_DEAL_*
     * @throws waDbException
     * @throws waException
     * @see crmRightConfig
     */
    public function deals($deals, $options = [])
    {
        $deal_type = $this->getInputArrayType($deals);
        if (!$deal_type) {
            return [];
        }

        $options = is_array($options) ? $options : [];

        $deal_ids = [];
        if ($deal_type === 'array') {
            foreach ($deals as $idx => $deal) {
                $deal_ids[$idx] = $deal['id'];
            }
        } else {
            $deal_ids = $deals;
        }

        if ($this->isAdmin()) {
            return array_fill_keys($deal_ids, crmRightConfig::RIGHT_DEAL_ALL);
        }

        if ($deal_type == 'int') {
            $dm = new crmDealModel();
            $deals = $dm->getById($deals);
        }

        $deal = reset($deals);
        if (!isset($deal['participants'])) {
            $dpm = new crmDealParticipantsModel();
            $participants = $dpm->getParticipants($deal_ids, true);
            foreach ($deals as &$deal) {
                $id = $deal['id'];
                $ps = [];
                if (isset($participants[$id])) {
                    $ps = $participants[$id];
                }
                $deal['participants'] = $ps;
            }
            unset($deal);
        }

        $ignore_contact_rights = (bool)ifset($options['ignore_contact_rights']);

        $result = [];
        foreach ($deals as $deal) {
            $id = $deal['id'];
            $level = $this->deal($deal, $ignore_contact_rights);
            $result[$id] = $level;
        }

        return $result;

    }

    /**
     * Get type of array that is input in list-oriented methods of this class
     * @param int[]|array $array - list of IDs OR just array
     * @return null|string - 'int' - list of ints (of IDs), 'array' - just array and NULL - undefined
     */
    protected function getInputArrayType($array)
    {
        if (empty($array) || !is_array($array)) {
            return null;
        }

        // define type of item in array
        $item = reset($array);
        if (wa_is_int($item)) {
            $input_type = 'int';
        } else if (is_array($item) && !empty($item)) {
            $input_type = 'array';
        } else {
            $input_type = null;
        }

        // unknown item type
        if (!$input_type) {
            return null;
        }

        // drop type incompatibility items
        foreach ($array as $idx => $item) {
            if (wa_is_int($item)) {
                $item_type = 'int';
            } else if (is_array($item) && !empty($item)) {
                $item_type = 'array';
            } else {
                $item_type = null;
            }
            if ($item_type != $input_type) {
                unset($array[$idx]);
            }
        }

        if (!$array) {
            return null;
        }

        return $item_type;
    }

    protected function isDealUserParticipant($deal)
    {
        // a little bit optimization - already has participants
        if (isset($deal['participants']) && is_array($deal['participants'])) {
            foreach ($deal['participants'] as $participant) {
                $is_participant = isset($participant['contact_id']) && $participant['contact_id'] == $this->contact->getId();
                $is_user = isset($participant['role_id']) && $participant['role_id'] == 'USER';
                if ($is_participant && $is_user) {
                    return true;
                }
            }
        } else {
            $dpm = new crmDealParticipantsModel();
            return (bool)$dpm->getByField(array(
                'contact_id' => $this->contact->getId(),
                'deal_id'    => $deal['id'],
                'role_id'    => 'USER'
            ));
        }
        return false;
    }

    public function reminderEditable($reminder)
    {
        return $this->contact->isAdmin('crm')
            || $reminder['user_contact_id'] == $this->contact->getId()
            || $reminder['creator_contact_id'] == $this->contact->getId();
    }

    public function getFunnelRights()
    {
        if ($this->isAdmin()) {
            return array();
        }

        return $this->contact->getRights('crm', 'funnel.%');
    }

    public function getAvailableVaultIds()
    {
        if ($this->isAdmin()) {
            return array();
        }

        // Public contacts
        $vault_ids = array(0 => true);

        // Contacts in vaults allowed for current user
        $vault_ids += $this->contact->getRights('crm', 'vault.%');

        // Private contacts current user has access to
        $adhoc_group_model = new crmAdhocGroupModel();
        foreach($adhoc_group_model->getByContact($this->contact->getId()) as $adhoc_id) {
            $vault_ids[-$adhoc_id] = true;
        }

        return array_keys($vault_ids);
    }

    /**
     * This method returns the flag: can the current user make an outgoing call using the telephony plug-in.
     * @return bool
     * @throws Exception
     */
    public function isInitCall()
    {
        $result = false;

        // Telephony plugins
        $pbx_plugins = wa('crm')->getConfig()->getTelephonyPlugins();
        if (!$pbx_plugins) {
            return $result;
        }

        // Pbx numbers
        $pbx_users_model = new crmPbxUsersModel();
        $user_pbx_numbers = $pbx_users_model->getByContact(wa()->getUser()->getId());
        if (empty($user_pbx_numbers)) {
            return $result;
        }

        foreach ($user_pbx_numbers as $number) {
            if (isset($pbx_plugins[$number['plugin_id']])) {
                $result = $pbx_plugins[$number['plugin_id']]->isInitCallAllowed();
                if ($result) {
                    return $result;
                }
            }
        }

        return $result;
    }

    /**
     * @return int
     */
    public function getConversationsRights()
    {
        return $this->contact->getRights('crm', 'conversations');
    }

    /**
     * Has view access to certain message, single item access (not list context)
     * Works as access to conversation
     * Do not use in loop, for loop use dropUnallowedMessages
     * @param array|int $message - ID or array record from DB
     * @return bool
     * @throws waException
     * @see canViewConversation
     */
    public function canViewMessage($message)
    {
        $res = $this->dropUnallowedMessages(array($message));
        return !empty($res);
    }

    /**
     * Has view access to certain conversation, single item access (not list context)
     * Do not use in loop, for loop use dropUnallowedConversations
     * @see dropUnallowedConversations
     * @param array|int $conversation - ID or array record from DB
     * @return bool
     * @throws waException
     */
    public function canViewConversation($conversation)
    {
        $res = $this->dropUnallowedConversations(array($conversation));
        return !empty($res);
    }

    /**
     * Has edit access to certain conversation, single item access (not list context)
     * Do not use in loop, for loop use dropUnallowedConversations
     * @see dropUnallowedConversations
     * @param $conversation - ID or array record from DB
     * @return bool
     * @throws waException
     */
    public function canEditConversation($conversation)
    {
        $res = $this->dropUnallowedConversations(array($conversation), array(
            'access_type' => 'edit'
        ));
        return !empty($res);
    }

    /**
     * Drop conversations to which contact has not access ('view' or 'edit')
     *
     * @param array[]|int[] $conversations - list of db record (as array), or list id IDs
     *
     * @param array $options options
     *
     *   string $options['access_type'] - Access type to check: 'view', 'edit'. Default 'view'
     *
     *   string $options['contact_access_type'] - If no access to contact (client) then is no access to conversation: 'view', 'ignore'. Default is 'view'
     *
     *   string $options['deal_access_type'] - If level access to deal is lower as specified in this option, then is no access to conversation.
     *          See access levels to deal (crmRightConfig::RIGHT_DEAL_* constants). Default is crmRightConfig::RIGHT_DEAL_VIEW.
     *          Also you can specify 'ignore', than access right to deal won't matter and will be ignored
     *
     * @return array[]|int[] - Type of result the same as input type
     *
     * @see dropUnallowedMessages
     * @throws waException
     */
    public function dropUnallowedConversations($conversations, $options = array())
    {
        if (empty($conversations) || !is_array($conversations)) {
            return array();
        }

        if ($this->isAdmin()) {
            return $conversations;
        }

        $options = is_array($options) ? $options : array();

        // define type of item in array
        $conversation_type = $this->getInputArrayType($conversations);
        if (!$conversation_type) {
            return [];
        }

        $access_type = isset($options['access_type']) ? $options['access_type'] : 'view';
        if ($access_type != 'edit' && $access_type != 'view') {
            $access_type = 'view';
        }

        // If contact has max access level to "conversation lists" (>= crmRightConfig::RIGHT_CONVERSATION_ALL),
        // then it means if that contact can see conversation then he/she can edit it as well (i.e. 'view' == 'edit' in this case)
        $conversation_rights = $this->getConversationsRights();
        if ($conversation_rights >= crmRightConfig::RIGHT_CONVERSATION_ALL) {
            $access_type = 'view';
        }

        if ($conversation_type == 'int') {
            $cm = new crmConversationModel();
            $conversations = $cm->getById($conversations);
        }

        // drop type incompatibility items: each item must has list of important keys
        foreach ($conversations as $idx => $conversation) {

            //
            $important_fields = array('id', 'deal_id', 'contact_id');
            if ($access_type === 'edit') {
                $important_fields[] = 'user_contact_id';
            }

            foreach ($important_fields as $key) {
                if (!array_key_exists($key, $conversation)) {
                    unset($conversations[$idx]);
                }
            }
        }

        if (!$conversations) {
            return array();
        }

        $current_contact_id = $this->contact->getId();

        // optimization edit type right: drop that conversations that could not pass user_contact_id check
        if ($access_type === 'edit') {
            foreach ($conversations as $idx => $conversation) {
                if ($conversation['user_contact_id'] && $conversation['user_contact_id'] != $current_contact_id) {
                    unset($conversations[$idx]);
                }
            }
            if (!$conversations) {
                return [];
            }
        }

        $contact_access_type = isset($options['contact_access_type']) ? $options['contact_access_type'] : 'view';
        if ($contact_access_type != 'view' && $contact_access_type != 'ignore') {
            $contact_access_type = 'view';
        }

        // map <id> => bool of allowed contacts, null means 'ignore' case
        $allowed_client_contacts = null;

        if ($contact_access_type != 'ignore') {

            // IDs of client contacts in conversations, typecast
            $client_contact_ids = waUtils::getFieldValues($conversations, 'contact_id');
            $client_contact_ids = waUtils::toIntArray($client_contact_ids);
            $client_contact_ids = waUtils::dropNotPositive($client_contact_ids);
            $client_contact_ids = array_unique($client_contact_ids);

            // drop unallowed contacts
            $client_contact_ids = $this->dropUnallowedContacts($client_contact_ids, $contact_access_type);
            $allowed_client_contacts = array_fill_keys($client_contact_ids, true);
        }

        $deal_access_type = isset($options['deal_access_type']) ? $options['deal_access_type'] : crmRightConfig::RIGHT_DEAL_VIEW;
        if (!wa_is_int($deal_access_type) && $deal_access_type != 'ignore') {
            $deal_access_type = crmRightConfig::RIGHT_DEAL_VIEW;
        }

        // map <id> => bool of allowed deals, null means 'ignore' case
        $allowed_deals = null;
        if ($deal_access_type != 'ignore') {

            // IDs of deals in conversations, typecast
            $deal_ids = waUtils::getFieldValues($conversations, 'deal_id');
            $deal_ids = waUtils::toIntArray($deal_ids);
            $deal_ids = waUtils::dropNotPositive($deal_ids);
            $deal_ids = array_unique($deal_ids);

            // extract deals
            $dm = new crmDealModel();
            $deals = $dm->getById($deal_ids);
            foreach ($deals as &$deal) {
                $deal['participants'] = array();
            }
            unset($deal);

            // only existed deals
            $deal_ids = array_keys($deals);

            // extract participants
            $dpm = new crmDealParticipantsModel();
            $participants = $dpm->getByField('deal_id', $deal_ids, true);

            // fill deals with participants
            foreach ($participants as $participant) {
                $deals[$participant['deal_id']]['participants'][] = $participant;
            }

            // drop unallowed logic
            foreach ($deals as $deal_id => $deal) {
                $deal_access = $this->deal($deal);
                if ($deal_access < $deal_access_type) {
                    unset($deals[$deal_id]);
                }
            }

            $deal_ids = array_keys($deals);
            $allowed_deals = array_fill_keys($deal_ids, true);
        }

        // now we can drop unallowed conversations
        foreach ($conversations as $idx => $conversation) {

            // check access to client contact
            $client_contact_id = $conversation['contact_id'];
            if ($allowed_client_contacts !== null && empty($allowed_client_contacts[$client_contact_id])) {
                unset($conversations[$idx]);
                continue;
            }

            // check access to deal
            if ($conversation['deal_id'] > 0) {
                $deal_id = $conversation['deal_id'];
                if ($allowed_deals !== null && empty($allowed_deals[$deal_id])) {
                    unset($conversations[$idx]);
                    continue;
                }
            }

            // NOTICE: edit access type check (by $conversation['user_contact_id'], already checked earlier in optimization block)

        }

        if ($conversation_type === 'int') {
            $conversations = array_keys($conversations);
        }

        return $conversations;

    }

    /**
     *
     * Drop messages to which contact has not view access ('edit' access for messages not exists)
     *
     * @param array[]|int[] $messages - list of db record (as array), or list id IDs
     * @param array $options
     *
     *   string $options['contact_access_type'] - If no access to contact (client) then is no access to conversation: 'view', 'ignore'. Default is 'view'
     *
     *   string $options['deal_access_type'] - If level access to deal is lower as specified in this option, then is no access to conversation.
     *          See access levels to deal (crmRightConfig::RIGHT_DEAL_* constants). Default is crmRightConfig::RIGHT_DEAL_VIEW.
     *          Also you can specify 'ignore', than access right to deal won't matter and will be ignored
     *
     * @return array[]|int[] - Type of result the same as input type
     *
     * @see dropUnallowedConversations
     *
     * @throws waException
     */
    public function dropUnallowedMessages($messages, $options = array())
    {
        if (empty($messages) || !is_array($messages)) {
            return array();
        }
        if ($this->isAdmin()) {
            return $messages;
        }

        // define type of item in array
        $message = reset($messages);
        if (wa_is_int($message)) {
            $message_type = 'int';
        } else if (is_array($message) && !empty($message)) {
            $message_type = 'array';
        } else {
            $message_type = null;
        }

        // unknown item type
        if (!$message_type) {
            return array();
        }

        // drop type incompatibility items
        foreach ($messages as $idx => $message) {
            if (wa_is_int($message)) {
                $item_type = 'int';
            } else if (is_array($message) && !empty($message)) {
                $item_type = 'array';
            } else {
                $item_type = null;
            }
            if ($item_type != $message_type) {
                unset($messages[$idx]);
            }
        }

        if (!$messages) {
            return array();
        }

        if ($message_type == 'int') {
            $cm = new crmMessageModel();
            $messages = $cm->getById($messages);
        }

        // drop type incompatibility items: each item must has list of important keys
        foreach ($messages as $idx => $message) {
            $important_fields = array('id', 'conversation_id');
            foreach ($important_fields as $key) {
                if (!array_key_exists($key, $message)) {
                    unset($messages[$idx]);
                }
            }
        }

        if (!$messages) {
            return array();
        }

        $contact_access_type = isset($options['contact_access_type']) ? $options['contact_access_type'] : 'view';
        if ($contact_access_type != 'view' && $contact_access_type != 'ignore') {
            $contact_access_type = 'view';
        }

        // map <id> => bool of allowed contacts, null means 'ignore' case
        $allowed_client_contacts = null;

        if ($contact_access_type != 'ignore') {

            // IDs of client contacts in messages, typecast
            $client_contact_ids = waUtils::getFieldValues($messages, 'contact_id');
            $client_contact_ids = waUtils::toIntArray($client_contact_ids);
            $client_contact_ids = waUtils::dropNotPositive($client_contact_ids);
            $client_contact_ids = array_unique($client_contact_ids);

            // drop unallowed contacts
            $client_contact_ids = $this->dropUnallowedContacts($client_contact_ids, $contact_access_type);
            $allowed_client_contacts = array_fill_keys($client_contact_ids, true);
        }

        $deal_access_type = isset($options['deal_access_type']) ? $options['deal_access_type'] : crmRightConfig::RIGHT_DEAL_VIEW;
        if (!wa_is_int($deal_access_type) && $deal_access_type != 'ignore') {
            $deal_access_type = crmRightConfig::RIGHT_DEAL_VIEW;
        }

        // map <id> => bool of allowed deals, null means 'ignore' case
        $allowed_deals = null;
        if ($deal_access_type != 'ignore') {

            // IDs of deals in conversations, typecast
            $deal_ids = waUtils::getFieldValues($messages, 'deal_id');
            $deal_ids = waUtils::toIntArray($deal_ids);
            $deal_ids = waUtils::dropNotPositive($deal_ids);
            $deal_ids = array_unique($deal_ids);

            // extract deals
            $dm = new crmDealModel();
            $deals = $dm->getById($deal_ids);
            foreach ($deals as &$deal) {
                $deal['participants'] = array();
            }
            unset($deal);

            // only existed deals
            $deal_ids = array_keys($deals);

            // extract participants
            $dpm = new crmDealParticipantsModel();
            $participants = $dpm->getByField('deal_id', $deal_ids, true);

            // fill deals with participants
            foreach ($participants as $participant) {
                $deals[$participant['deal_id']]['participants'][] = $participant;
            }

            // drop unallowed logic
            foreach ($deals as $deal_id => $deal) {
                $deal_access = $this->deal($deal);
                if ($deal_access < $deal_access_type) {
                    unset($deals[$deal_id]);
                }
            }

            $deal_ids = array_keys($deals);
            $allowed_deals = array_fill_keys($deal_ids, true);
        }

        // now we can drop unallowed conversations
        foreach ($messages as $idx => $message) {

            // check access to client contact
            $client_contact_id = $message['contact_id'];
            if ($allowed_client_contacts !== null && empty($allowed_client_contacts[$client_contact_id])) {
                unset($messages[$idx]);
                continue;
            }

            // check access to deal
            if ($message['deal_id'] > 0) {
                $deal_id = $message['deal_id'];
                if ($allowed_deals !== null && empty($allowed_deals[$deal_id])) {
                    unset($messages[$idx]);
                    continue;
                }
            }

        }

        if ($message_type === 'int') {
            $messages = array_keys($messages);
        }

        return $messages;
    }

    /**
     * @param array $call item crmCallModel
     * @return bool
     * @throws waDbException
     * @throws waException
     */
    public function call($call = [])
    {
        $call_right = $this->contact->getRights('crm', 'calls');
        if ($call_right == crmRightConfig::RIGHT_CALL_NONE) {
            return false;
        } elseif ($call_right == crmRightConfig::RIGHT_CALL_OWN) {
            if (ifset($call, 'user_contact_id', 0) !== $this->contact->getId()) {
                return false;
            }
            if (!empty($call['client_contact_id'])) {
                if (!$this->contact($call['client_contact_id'])) {
                    return false;
                }
            }
            if (!empty($call['deal_id'])) {
                if ($this->deal($call['deal_id']) === crmRightConfig::RIGHT_DEAL_NONE) {
                    return false;
                }
            }
        }

        return true;
    }
}
