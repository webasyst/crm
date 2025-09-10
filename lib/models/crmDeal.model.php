<?php

class crmDealModel extends crmModel
{
    /**
     * @var crmDealParticipantsModel
     */
    protected $dpm;

    /**
     * @var crmCurrencyModel
     */
    protected $cm;

    /**
     * @var crmLogModel
     */
    protected $lm;

    protected $table = 'crm_deal';

    /**
     * @var array[]string
     */
    protected $link_contact_field = array('creator_contact_id', 'contact_id', 'user_contact_id');

    /**
     * count and unset contacts link behavior redefined in unsetContactLinks and getContactLinksCount
     * @see getContactLinksCount
     * @see unsetContactLinks
     * @var null
     */
    protected $unset_contact_links_behavior = null;

    const LOG_ACTION_ADD = 'deal_add';
    const LOG_ACTION_UPDATE = 'deal_edit';

    const STATUS_OPEN = 'OPEN';
    const STATUS_WON = 'WON';
    const STATUS_LOST = 'LOST';

    public function addParticipants($deal, $participant, $role, $label = null)
    {
        if (empty($deal['id']) || is_numeric($deal)) {
            $deal = $this->getById($deal);
        }
        if (!$deal || $deal['id'] <= 0) {
            return false;
        }

        $pm = $this->getParticipantsModel();

        $roles = array_fill_keys($pm->getRoles(), true);

        $participants = array();
        foreach ((array)$participant as $item) {

            if (!is_array($item)) {
                $item = array('contact_id' => $item);
            }

            $item['contact_id'] = (int)ifset($item['contact_id']);
            if ($item['contact_id'] <= 0) {
                continue;
            }

            $item['role_id'] = ifempty($item['role_id'], $role);
            if (!isset($roles[$item['role_id']])) {
                $item['role_id'] = crmDealParticipantsModel::ROLE_CLIENT;
            }
            $item['label'] = ifempty($item['label'], $label);

            $item['deal_id'] = $deal['id'];
            $participants[] = $item;
        }

        if (!$participants) {
            return false;
        }

        $this->getParticipantsModel()->multipleInsert($participants);

        return true;
    }

    public function updateParticipant($deal_id, $contact_id, $field, $label = null)
    {
        if ($field == 'contact_id') {
            $role = crmDealParticipantsModel::ROLE_CLIENT;
        } else {
            $role = crmDealParticipantsModel::ROLE_USER;
            $field == 'user_contact_id';
        }
        $deal = $this->getById($deal_id);
        if (!$deal) {
            throw new waException(_w('Deal not found'));
        }
        $this->updateById($deal_id, array($field => $contact_id));

        $dpm = new crmDealParticipantsModel();
        $dpm->deleteByField(array('deal_id' => $deal_id, 'contact_id' => $deal[$field], 'role_id' => $role));
        $dpm->replace(array('deal_id' => $deal_id, 'contact_id' => $contact_id, 'role_id' => $role, 'label' => $label));
    }

    /**
     * @param int|int[] $id
     * @param array $options
     *      string[] $options['reset'] - list of relations that need to reset (not delete by default)
     */
    public function delete($id, $options = [])
    {
        $ids = crmHelper::toIntArray($id);
        $ids = crmHelper::dropNotPositive($ids);
        if (!$ids) {
            return;
        }

        $options = is_array($options) ? $options : [];
        $reset = isset($options['reset']) && is_array($options['reset']) ? $options['reset'] : [];
        $reset = array_fill_keys($reset, true);

        $this->deleteById($ids);
        $this->getDealParticipantsModel()->deleteByField('deal_id', $ids);

        if (!empty($reset['message'])) {
            $this->getMessageModel()->updateByField(['deal_id' => $ids], ['deal_id' => null]);
        } else {
            $this->getMessageModel()->deleteByDeal($ids);
        }

        if (!empty($reset['conversation'])) {
            $this->getConversationModel()->updateByField(['deal_id' => $ids], ['deal_id' => null]);
        } else {
            $this->getConversationModel()->deleteByDeal($ids);
        }

        $contact_ids = array_map(wa_lambda('$id', 'return -$id;'), $ids);
        $this->getReminderModel()->deleteByContact($contact_ids);
        $this->getNoteModel()->deleteByContact($contact_ids);
        $this->getFileModel()->deleteByContact($contact_ids);
    }

    /**
     * @param $group_id
     * @return int|null
     */
    public function getResponsibleUserOfGroup($group_id)
    {
        $sql = "SELECT wug.contact_id
                FROM wa_user_groups wug
                JOIN wa_contact c ON c.id = wug.contact_id AND c.is_user != -1
                LEFT JOIN `{$this->table}` cd ON cd.user_contact_id = wug.contact_id AND cd.status_id = :status
                WHERE wug.group_id = :group_id
                GROUP BY wug.contact_id
                ORDER BY COUNT(cd.id)
                LIMIT 1";

        $user_contact_id = (int)$this->query($sql, array('group_id' => $group_id, 'status' => self::STATUS_OPEN))->fetchField();
        return $user_contact_id > 0 ? $user_contact_id : null;
    }

    public function isRelated($id, $contact_id, $role_id)
    {
        return $this->getParticipantsModel()->exists($id, $contact_id, $role_id);
    }

    public function getParticipantsModel()
    {
        return $this->dpm !== null ? $this->dpm : ($this->dpm = new crmDealParticipantsModel());
    }

    /**
     * @param array $deal . Fields of deal-record + special keys
     *   - 'files' - array[]waRequestFile files to attach
     *   - 'params' - array[]string key => value map of params
     * @return bool|int
     * @throws waDbException
     * @throws waException
     * @see attachFiles
     */
    public function add($deal)
    {
        $deal['create_datetime'] = $deal['update_datetime'] = date('Y-m-d H:i:s');

        $currency = $this->getCurrencyInfo(ifset($deal['currency_id']));
        $deal['currency_id'] = $currency['code'];
        $deal['currency_rate'] = $currency['rate'];

        // User responsible for the deal is taken from contact
        // unless explicitly set in deal
        $contact_responsible = "";
        if ((int)$deal['contact_id'] && !array_key_exists('user_contact_id', $deal)) {
            try {
                $contact = new waContact($deal['contact_id']);
                $contact_responsible = $contact['crm_user_id'];

                if (!isset($deal['user_contact_id']) && $contact_responsible) {
                    $deal['user_contact_id'] = $contact_responsible;
                }
            } catch (Exception $e) {
                $contact_responsible = null;
            }
        }

        if (!array_key_exists('creator_contact_id', $deal)) {
            $deal['creator_contact_id'] = wa()->getUser()->getId();
        }
        if (!array_key_exists('user_contact_id', $deal)) {
            $deal['user_contact_id'] = $deal['creator_contact_id'];
        }
        $deal['user_contact_id'] = (int)ifset($deal['user_contact_id']);

        $deal['funnel_id'] = (int)ifset($deal['funnel_id']);
        $deal['stage_id'] = (int)ifset($deal['stage_id']);

        if ($deal['funnel_id'] <= 0) {
            $deal['funnel_id'] = null;
            $deal['stage_id'] = null;
        }

        $id = $this->insert($deal);
        if (!$id) {
            return false;
        }

        $dpm = $this->getParticipantsModel();

        // User responsible for deal
        if ($deal['user_contact_id'] > 0) {
            $dpm->insert(array(
                'deal_id'    => $id,
                'contact_id' => $deal['user_contact_id'],
                'role_id'    => 'USER'
            ));
        }
        // Client contact
        $dpm->insert(array(
            'deal_id'    => $id,
            'contact_id' => $deal['contact_id'],
            'role_id'    => 'CLIENT'
        ));
        // User responsible for the contact
        if ($contact_responsible && $contact_responsible != $deal['user_contact_id']) {
            $dpm->insert(array(
                'deal_id'    => $id,
                'contact_id' => $contact_responsible,
                'role_id'    => 'USER'
            ));
        }

        $dsm = new crmDealStagesModel();
        $dsm->open($id, $deal['stage_id']);

        $deal['id'] = $id;

        $crm_log_id = $this->getLogModel()->add(array(
            'actor_contact_id' => $deal['creator_contact_id'],
            'contact_id'       => -$id,
            'object_id'        => $id,
            'object_type'      => crmLogModel::OBJECT_TYPE_DEAL,
            'action'           => self::LOG_ACTION_ADD,
        ));
        $params = [
            'deal'       => $deal,
            'crm_log_id' => $crm_log_id
        ];

        /**
         * @event deal_create
         * @param array $deal
         * @return bool
         */
        wa('crm')->event('deal_create', $params);

        $files = ifset($deal['files']);
        $this->attachFiles($id, $files);

        $params = ifset($deal['params']);
        $this->setParams($id, $params);

        return $id;
    }

    /**
     * @param int $deal_id
     * @param $files
     * @return array
     * @throws waDbException
     * @throws waException
     */
    public function attachFiles($deal_id, $files)
    {
        $deal_id = (int)$deal_id;
        if ($deal_id <= 0 || !$files) {
            return array();
        }

        // attach files
        $res = array();
        $fm = new crmFileModel();
        foreach ($files as $index => $file) {
            if ($file instanceof waRequestFile) {
                $file_id = $fm->add(array('contact_id' => -$deal_id), $file);
                if ($file_id > 0) {
                    $res[$index] = $file_id;
                }
            }
        }

        return $res;
    }

    /**
     * @param int|array $deal
     * @return array
     * @throws waException
     */
    public function getParams($deal)
    {
        // extract params or get from db
        $params = array();
        if (is_array($deal) && isset($deal['params'])) {
            $params = (array)$deal['params'];
        } else {
            $deal_id = is_scalar($deal) ? (int)$deal : 0;
            if ($deal_id > 0) {
                $params = $this->getDealParamsModel()->get($deal_id);
            }
        }
        return $params;
    }

    /**
     * @param int|array $deal
     * @param array[] string $params
     * @throws waException
     */
    public function setParams($deal, $params)
    {
        // typecast
        if (is_array($deal) && isset($deal['id'])) {
            $deal_id = (int)$deal['id'];
        } elseif (is_scalar($deal)) {
            $deal_id = (int)$deal;
        } else {
            $deal_id = 0;
        }

        // guard case
        if ($deal_id <= 0) {
            return;
        }

        // typecast
        $params = array_map('strval', (array)$params);

        // guard case
        if (!$params) {
            return;
        }

        // set params, params - it's a deal-field values

        $params_to_set = array();
        foreach (crmDealFields::getAll('enabled') as $field) {
            $field_id = $field->getId();
            if (isset($params[$field_id])) {
                $value = $params[$field_id];
                $params_to_set[$field_id] = $field->typecast($value);
            }
        }
        if (!empty($params['!form_page_url'])) {
            $params_to_set['!form_page_url'] = $params['!form_page_url'];
        }

        // guard case
        if (!$params_to_set) {
            return;
        }

        $this->getDealParamsModel()->set($deal_id, $params_to_set);
    }

    /**
     * @param $id
     * @param $deal  Fields of deal-record + special keys
     *   - 'params' - array[]string key => value map of params
     * @param $before_deal
     * @return bool
     * @throws waException
     */
    public function update($id, $deal, $before_deal = [])
    {
        if (isset($deal['currency_id'])) {
            $currency = $this->getCurrencyInfo($deal['currency_id']);
            $deal['currency_id'] = $currency['code'];
            $deal['currency_rate'] = $currency['rate'];
        }

        if (empty($before_deal)) {
            $before_deal = $this->getDeal($id, false, true);
        }

        $log_data = [];
        $before_params = ifset($before_deal, 'params', $this->getParams($id));
        $after_params = ifset($deal['params']);
        unset($deal['params']);

        //gorizontal data
        foreach ($deal as $_field => $_value) {
            if (
                $_field != 'description'
                && !empty($before_deal[$_field])
                && $_value != $before_deal[$_field]
            ) {
                $log_data[] = [
                    'field'  => $_field,
                    'before' => $before_deal[$_field],
                    'after'  => $_value
                ];
            }
        }

        //vertical data
        foreach (array_keys($before_params + $after_params) as $_param_name) {
            if (
                isset($before_params[$_param_name], $after_params[$_param_name])
                && $before_params[$_param_name] == $after_params[$_param_name]
                || crmDealFields::get($_param_name)->getType() == 'Text'
            ) {
                continue;
            }
            $log_data[] = [
                'field'  => $_param_name,
                'before' => ifset($before_params, $_param_name, null),
                'after'  => ifset($after_params, $_param_name, null)
            ];
        }

        if (!$this->updateById($id, $deal)) {
            return false;
        }

        $this->setParams($id, $after_params);
        $this->getLogModel()->log(
            self::LOG_ACTION_UPDATE,
            -$id,
            $id,
            null,
            null,
            null,
            $log_data
        );

        return $id;
    }

    public function updateById($id, $data, $options = null, $return_object = false)
    {
        if (empty($data['stage_id']) && empty($data['status_id'])) {
            return parent::updateById($id, $data, $options, $return_object);
        }

        $deal = $this->getById($id);
        if (!$deal) {
            return false;
        }

        $update_result = parent::updateById($id, $data, $options, $return_object);
        if (!$update_result) {
            return $update_result;
        }

        $event_data = array('deal' => $data + $deal);
        $event_data['crm_log_id'] = ifempty($data, 'crm_log_id', 0);
        unset($data['crm_log_id']);

        if (!empty($data['stage_id']) && $deal['stage_id'] != $data['stage_id']) {
            $dsm = new crmDealStagesModel();
            $dsm->close($id, $deal['stage_id']);
            $dsm->open($id, $data['stage_id'], $deal['stage_id']);
            $event_data['deal']['before_stage_id'] = $deal['stage_id'];
            $event_data['deal']['before_funnel_id'] = $deal['funnel_id'];
            /**
             * @event deal_move
             * @param array $deal
             * @return bool
             */
            wa('crm')->event('deal_move', $event_data);
        } elseif (!empty($data['status_id']) && $data['status_id'] != 'OPEN' && $deal['status_id'] == 'OPEN') {
            $dsm = new crmDealStagesModel();
            $dsm->close($id, $deal['stage_id']);
            /**
             * @event deal_won | deal_lost
             * @param array $deal
             * @return bool
             */
            wa('crm')->event('deal_'.strtolower($data['status_id']), $event_data); // won/lost
        } elseif (!empty($data['status_id']) && $data['status_id'] == 'OPEN' && $deal['status_id'] != 'OPEN') {
            $dsm = new crmDealStagesModel();
            $dsm->open($id, $deal['stage_id']);
            $event_data = [
                'deal'       => $deal,
                'crm_log_id' => $event_data['crm_log_id']
            ];
            /**
             * @event deal_restore
             * @param array $deal
             * @return bool
             */
            wa('crm')->event('deal_restore', $event_data);
        }

        return $update_result;
    }

    /**
     * Received extra processed deal record
     *
     * @param $id
     * @param bool $with_participants
     * @param bool $with_params if set to true than receive 'params' and 'fields'
     *
     * @return null|array $deal
     *   Extra fields for deal in case if not NULL
     *     array $deal['files'] Files records received from crmFileModel
     *     string $deal['description_sanitized'] Description of deal, sanitized and ready for safe rendering in browser
     *     array $deal['participants'] Participants if proper flag is set to true
     *     array $deal['params'] Params
     *     array $deal['fields'] Array of deal-fields info items with raw and formatted value
     *
     * @throws waException
     * @see crmDealParticipantsModel
     * @see crmDealParamsModel
     * @see crmHtmlSanitizer
     * @see crmFileModel
     */
    public function getDeal($id, $with_participants = false, $with_params = false)
    {
        $deal = $this->getById($id);
        if (!$deal) {
            return null;
        }

        $files = $this->getFileModel()->getFilesByField(array('contact_id' => -$deal['id']));
        $deal['files'] = $files;

        // sanitize html, replace img src with
        $replace_img_src = array();
        $app_url = wa()->getAppUrl('crm');
        foreach ($deal['files'] as $file) {
            $replace_img_src[$file['id']] = "{$app_url}deal/{$id}/?module=file&action=download&id={$file['id']}";
        }

        $sanitizer = new crmHtmlSanitizer([ 'replace_img_src' => $replace_img_src ]);
        $deal['description_sanitized'] = $sanitizer->sanitize($deal['description']);
        $deal['description_plain'] = $sanitizer->toPlainText($deal['description']);

        if ($with_participants) {
            $participants = $this->getParticipantsModel()->getParticipants($deal['id']);
            if (!empty($deal['contact_id']) && !in_array($deal['contact_id'], array_column($participants, 'contact_id'))) {
                $participants[] = [
                    'deal_id' => $deal['id'],
                    'contact_id' => $deal['contact_id'],
                    'role_id' => crmDealParticipantsModel::ROLE_CLIENT,
                    'label' => '',
                    'create_datetime' => $deal['create_datetime'],
                ];
                $this->addParticipants($deal, $deal['contact_id'], crmDealParticipantsModel::ROLE_CLIENT);
            }
            if (!empty($deal['user_contact_id']) && !in_array($deal['user_contact_id'], array_column($participants, 'contact_id'))) {
                $participants[] = [
                    'deal_id' => $deal['id'],
                    'contact_id' => $deal['user_contact_id'],
                    'role_id' => crmDealParticipantsModel::ROLE_USER,
                    'label' => '',
                    'create_datetime' => $deal['create_datetime'],
                ];
                $this->addParticipants($deal, $deal['user_contact_id'], crmDealParticipantsModel::ROLE_USER);
            }
            $deal['participants'] = $participants;
        }

        if ($with_params) {
            $deal['params'] = $this->getParams($deal['id']);
            $deal['fields'] = array();
            $fields = crmDealFields::getAll();

            $sources = $this->getDealSources($deal['id']);
            if ($sources) {
                $deal['fields']['source'] = array(
                    'id'              => "source",
                    'name'            => "Source",
                    'type'            => $sources['type'],
                    'value'           => $sources['name'],
                    'value_formatted' => $sources['name'],
                );
                if ($sources['type'] == "IM") {
                    $deal['fields']['source']['provider'] = $sources['provider'];
                    $deal['fields']['source']['icon'] = wa()->getAppStaticUrl('crm/plugins/' . $sources['provider'] . '/img', true) . $sources['provider'].'.png';
                }
            }

            foreach ($fields as $field_id => $field) {
                $info = $field->getInfo();
                $funnels_params = $field->getFunnelsParameters();

                $info['value'] = '';
                $info['value_formatted'] = '';
                if (isset($deal['params'][$field_id])) {
                    $info['value'] = $deal['params'][$field_id];
                    $info['value_formatted'] = $field->format($deal['params'][$field_id]);
                }
                $info['funnels_parameters'] = '';
                if (!empty($funnels_params) && !empty($funnels_params[$deal['funnel_id']])) {
                    $info['funnels_parameters'] = $funnels_params[$deal['funnel_id']];
                }
                $deal['fields'][$field_id] = $info;
            }
        }

        return $deal;
    }

    public function getEmptyDeal($funnel_id = 1)
    {
        $deal = $this->getEmptyRow();
        $deal['status_id'] = self::STATUS_OPEN;
        $deal['description_sanitized'] = '';
        $deal['files'] = array();
        $deal['participants'] = array();
        $deal['params'] = array();
        $fields = crmDealFields::getAll('enabled');

        foreach ($fields as $field_id => $field) {
            $info = $field->getInfo();
            $funnels_params = $field->getFunnelsParameters();
            $info['value'] = '';
            $info['value_formatted'] = '';
            $info['funnels_parameters'] = '';
            if (!empty($funnels_params) && !empty($funnels_params[$funnel_id])) {
                $info['funnels_parameters'] = $funnels_params[$funnel_id];
            }
            $deal['fields'][$field_id] = $info;
        }

        return $deal;
    }

    public function getDealSources($deal_id)
    {
        if (!empty($deal_id)) {
            $sql = "SELECT s.id, s.type, s.provider, s.name FROM crm_deal d
                      INNER JOIN crm_source s ON s.id = d.source_id
                    WHERE d.id = " . intval($deal_id) . " AND d.source_id != ''";
            return $this->query($sql)->fetchAssoc();
        }
    }

    protected function getCurrencyInfo($currency_id)
    {
        $currency = null;
        $currency_id = (string)$currency_id;
        if (strlen($currency_id) > 0) {
            $currency = $this->getCurrencyModel()->getById($currency_id);
        }
        if (!$currency) {
            $primary_currency = waCurrency::getInfo(wa('crm')->getConfig()->getCurrency());
            $primary_currency_code = ifset($primary_currency, 'code', wa()->getLocale() == 'ru_RU' ? 'RUB' : 'USD');

            $currency = [
                'code' => $primary_currency_code,
                'rate' => 1.0
            ];
        }
        return $currency;
    }

    protected function getCurrencyModel()
    {
        return $this->cm !== null ? $this->cm : ($this->cm = new crmCurrencyModel());
    }

    protected function getLogModel()
    {
        return $this->lm !== null ? $this->lm : ($this->lm = new crmLogModel());
    }

    /**
     * @param array[] int $contact_ids
     * Count deals by participants without access rights filtering
     * @return array|int
     */
    public function countByParticipants($contact_ids)
    {
        $contact_ids = array_filter(array_map('intval', (array)$contact_ids));
        if (empty($contact_ids)) {
            return array();
        }

        $sql = "SELECT COUNT(DISTINCT `deal_id`)
                FROM crm_deal_participants
                WHERE role_id='CLIENT'
                    AND contact_id IN (".join(',', $contact_ids).")";
        return (int)$this->query($sql)->fetchField();
    }

    /**
     * Count deals of each person client
     *
     * @param int[] $contact_ids
     * @return int[] counters
     *   Map from contact_id to count: <contact_id> => <count>.
     *   If for <contact_id> not found needed counter in DB, returned array will has <count_id> => 0 anyway
     *
     * @see countByCompanyClients
     */
    public function countByPersonClients($contact_ids)
    {
        if (!is_array($contact_ids) || empty($contact_ids)) {
            return array();
        }
        $contact_ids = waUtils::toIntArray($contact_ids);
        $contact_ids = waUtils::dropNotPositive($contact_ids);
        $contact_ids = array_unique($contact_ids);

        if (!$contact_ids) {
            return array();
        }

        $counters = array_fill_keys($contact_ids, 0);

        // for person contacts case is simple, for companies it is a little bit tricky, see countByCompanyClients
        $sql = "SELECT participant.contact_id, COUNT(DISTINCT participant.deal_id) AS cnt
                    FROM `crm_deal_participants` participant
                    WHERE participant.contact_id IN (:ids) AND participant.role_id = 'CLIENT'
                    GROUP BY participant.contact_id";

        $db_result = $this->query($sql, array("ids" => $contact_ids));
        foreach ($db_result as $item) {
            $counters[$item['contact_id']] = $item['cnt'];
        }

        return $counters;
    }

    /**
     * Count deals of each company client, with taking into account employees
     * @param int[] $contact_ids
     * @return int[] counters
     *   Map from contact_id to count: <contact_id> => <count>.
     *   If for <contact_id> not found needed counter in DB, returned array will has <count_id> => 0 anyway
     *
     * @see countByPersonClients
     */
    public function countByCompanyClients($contact_ids)
    {
        if (!is_array($contact_ids) || empty($contact_ids)) {
            return array();
        }
        $contact_ids = waUtils::toIntArray($contact_ids);
        $contact_ids = waUtils::dropNotPositive($contact_ids);
        $contact_ids = array_unique($contact_ids);

        if (!$contact_ids) {
            return array();
        }

        $counters = array_fill_keys($contact_ids, 0);

        // take into account employees (relation by company_contact_id)
        // Essence: for each company count all distinct deals of all employees AND company itself (hence OR in 2nd join)
        // + Optimization hack: use CASE in join is faster than join with company.id = client.company_contact_id OR company.id = client.id
        $sql = "SELECT company.id, COUNT(DISTINCT participant.deal_id) AS cnt
                    FROM `crm_deal_participants` participant
                    JOIN `wa_contact` client ON client.id = participant.contact_id AND participant.role_id = 'CLIENT'
                    JOIN `wa_contact` company ON company.id =
                        CASE client.company_contact_id
                            WHEN 0
                                THEN client.id
                            ELSE
                                client.company_contact_id
                        END
                    WHERE company.id IN (:ids)
                    GROUP BY company.id";

        $db_result = $this->query($sql, array("ids" => $contact_ids));
        foreach ($db_result as $item) {
            $counters[$item['id']] = $item['cnt'];
        }

        return $counters;
    }

    public function getList($params, &$total_count = null)
    {
        // LIMIT
        if (isset($params['offset']) || isset($params['limit'])) {
            $offset = (int)ifset($params['offset'], 0);
            $limit = (int)ifset($params['limit'], 50);
            if (!$limit) {
                return array();
            }
        } else {
            $offset = $limit = null;
        }

        // ORDER BY
        $sort = 'd.id';
        $sort_join = '';
        $table_fields = $this->getMetadata();

        $order = ifset($params['order'], 'ASC');
        if (strtolower($order) !== 'asc') {
            $order = 'DESC';
        }

        if (!empty($params['sort'])) {
            if ($params['sort'] == 'user_name') {
                $sort_join = 'LEFT JOIN wa_contact uc ON uc.id=d.user_contact_id';
                $sort = 'uc.name';
                $user_name_display = preg_split('/\s*,\s*/', trim(wa()->getSetting('user_name_display', 'name', 'webasyst')));
                if (!empty($user_name_display[0])) {
                    $user_name_display = $this->escapeField(ifset($user_name_display[0]));
                    $sort = 'IFNULL(uc.'.$user_name_display.', uc.name)';
                }
            } elseif ($params['sort'] == 'amount') {
                $sort = 'd.amount * d.currency_rate';
            } elseif ($params['sort'] == 'funnel_id' || $params['sort'] == 'stage_id') {
                $sort_join = 'JOIN crm_funnel_stage fs ON fs.id=d.stage_id';
                $sort = array('d.funnel_id', 'd.status_id', 'fs.number');
            } elseif ($params['sort'] === 'last_action') {
                $sort = 'IFNULL(last_log_datetime, update_datetime)';
            } elseif ($params['sort'] === 'reminder_datetime') {
                $sort = 'reminder_datetime IS ' . (strtolower($order) === 'asc' ? 'NOT' : '') . ' NULL, reminder_datetime';
            } elseif (!empty($table_fields[$params['sort']])) {
                $sort = $this->escapeField($params['sort']);
                $sort = 'd.'.$sort;
            }
        }

        // WHERE: filter conditions
        $filter_conditions = $this->getWhereByField(array_intersect_key($params, $table_fields), 'd');
        if ($filter_conditions === '1=0') {
            return array();
        }

        // WHERE: access rights check
        $access_rights_join = '';
        $access_rights_conditions = '1=1';
        if (!empty($params['check_rights']) && !wa()->getUser()->isAdmin('crm')) {

            $rights = new crmRights();
            $access_rights_conditions = array();

            // Only keep available vaults
            $access_rights_join = ' JOIN wa_contact c ON c.id=d.contact_id ';
            $access_rights_conditions[] = "c.crm_vault_id IN (".join(',', $rights->getAvailableVaultIds()).")";

            // Only keep available/requested funnels
            $funnel_rights = $rights->getFunnelRights();
            if (isset($params['funnel_id'])) {
                $funnel_rights = array_intersect_key($funnel_rights, array_fill_keys((array)$params['funnel_id'], 1));
            }
            if (!$funnel_rights) {
                return array();
            }
            $access_rights_conditions[] = "d.funnel_id IN (".join(',', array_keys($funnel_rights)).")";

            // Some funnels only allow to see own deals
            $limited_funnels = array();
            $unlimited_funnels = array();
            $unassigned_deals = false;
            foreach ($funnel_rights as $fid => $rights_level) {
                if ($rights_level <= crmRightConfig::RIGHT_FUNNEL_OWN_UNASSIGNED) {
                    $limited_funnels[$fid] = $fid;
                    if ($rights_level >= crmRightConfig::RIGHT_FUNNEL_OWN_UNASSIGNED) {
                        $unassigned_deals = true;
                    }
                } else {
                    $unlimited_funnels[$fid] = $fid;
                }
            }
            if ($limited_funnels) {
                $cond = array();

                // Deals that belong to a user are allowed in limited funnels
                $cond[] = 'd.user_contact_id='.wa()->getUser()->getId();

                // Deals are allowed where user is added as a participant
                $cond[] = 'dpu.contact_id IS NOT NULL';
                $access_rights_join .=
                    " LEFT JOIN crm_deal_participants AS dpu
                        ON dpu.deal_id=d.id
                            AND dpu.role_id='USER'
                            AND dpu.contact_id=".wa()->getUser()->getId();

                // Deals in funnels with unlimited rights are allowed
                if ($unlimited_funnels) {
                    $cond[] = 'd.funnel_id IN ('.join(',', $unlimited_funnels).')';
                }
                if ($unassigned_deals) {
                    $cond[] = 'd.user_contact_id = 0';
                }

                $access_rights_conditions[] = '( '.join(' OR ', $cond).' )';
            }

            /*
            // !!! this should be used instead of an `if` above
            // when user_contact_id of a deal is added to crm_deal_participants.
            // Currently it's not tested and turned off here.
            if ($limited_funnels) {
                if ($unlimited_funnels) {
                    // Condition requires a LEFT JOIN and an OR
                    // when both limited and unlimited funnels are involved.
                    $or_cond = array();

                    // Deals in funnels with unlimited rights are allowed
                    $or_cond[] = 'd.funnel_id IN ('.join(',', $unlimited_funnels).')';

                    // Deals are allowed where user is added as a participant
                    $or_cond[] = 'dpu.contact_id IS NOT NULL';
                    $access_rights_join .=
                        " LEFT JOIN crm_deal_participants AS dpu
                            ON dpu.deal_id=d.id
                                AND dpu.role_id='USER'
                                AND dpu.contact_id=".wa()->getUser()->getId();

                    $access_rights_conditions[] = '( '.join(' OR ', $or_cond).' )';
                } else {
                    // When there are only limited funnels available for current user,
                    // a single INNER JOIN is enough.
                    $access_rights_join .=
                        " JOIN crm_deal_participants AS dpu
                            ON dpu.deal_id=d.id
                                AND dpu.role_id='USER'
                                AND dpu.contact_id=".wa()->getUser()->getId();
                }
            }*/

            $access_rights_conditions = join(' AND ', $access_rights_conditions);
        }

        // WHERE: filter by client participants
        $participants_join = '';
        $participants_condition = '1=1';
        if (isset($params['participants'])) {
            $contact_ids = array_filter(array_map('intval', (array)$params['participants']));
            if (empty($contact_ids)) {
                return array();
            }

            $participants_join =
                "LEFT JOIN crm_deal_participants AS dpc
                    ON dpc.deal_id=d.id
                        AND dpc.role_id='CLIENT'";
            $cond = array();
            $cond[] = "dpc.contact_id IN (".join(',', $contact_ids).")";
            $cond[] = "d.contact_id IN (".join(',', $contact_ids).")";
            $participants_condition = '( '.join(' OR ', $cond).' )';
        }

        // WHERE: filter by markers (unread messages and reminders)
        $markers_join = '';
        $markers_condition = '1=1';
        if (isset($params['reminder_state'])) {
            $markers_condition = "d.status_id = 'OPEN'";
            $conditions = array();
            if (isset($params['reminder_state']) && is_array($params['reminder_state'])) {
                foreach ($params['reminder_state'] as $state) {
                    switch ($state) {
                        case 'no':
                            $conditions[] = 'd.reminder_datetime IS NULL';
                            break;
                        case 'overdue':
                            $conditions[] = "(DATE(d.reminder_datetime) < '".date('Y-m-d')."' || (d.reminder_datetime < '".date('Y-m-d H:i:s')."'))";
                            break;
                        case 'burn':
                            $conditions[] = "(DATE(d.reminder_datetime) = '".date('Y-m-d')."' AND (d.reminder_datetime >= '".date('Y-m-d H:i:s')."'))";
                            break;
                        case 'actual':
                            $conditions[] = "(DATE(d.reminder_datetime) = '".date('Y-m-d')."' + INTERVAL 1 DAY)";
                            break;
                        case 'unread':
                            $markers_join = "LEFT JOIN crm_message_read AS mr
                                ON mr.message_id = d.last_message_id AND mr.contact_id = ".intval(wa()->getUser()->getId());
                            $conditions[] = '(d.last_message_id IS NOT NULL AND mr.message_id IS NULL)';
                            break;
                    }
                }
            }
            if ($conditions) {
                $markers_condition .= ' AND ('.join(' OR ', $conditions).')';
            }
        }

        // WHERE: filter by tag
        $tag_join = '';
        if (!empty($params['tag_id'])) {
            $tag_join .= "INNER JOIN crm_contact_tags AS ct ON ct.contact_id=-d.id AND tag_id=".intval($params['tag_id']);
        }

        if (!empty($params['start_date'])) {
            $filter_conditions .= " AND d.closed_datetime >= '".$this->escape($params['start_date'])."'";
        }
        if (!empty($params['end_date'])) {
            $filter_conditions .= " AND d.closed_datetime <= '".$this->escape($params['end_date'])."'";
        }
        if (isset($params['deal_ids']) && is_array($params['deal_ids'])) {
            $filter_conditions .= (empty($params['deal_ids']) ? " AND d.id = 0" : " AND d.id IN (".$this->escape(implode(',', $params['deal_ids'])).")");
        }

        // WHERE: filter by fields
        $fields_join = '';

        if (!empty($params['fields'])) {
            $i = 0;
            foreach ($params['fields'] as $field_key => $field_value) {
                if ($field_value != "") {
                    $fields_join .= " INNER JOIN crm_deal_params dpr{$i} ON dpr{$i}.deal_id = d.id
                                        AND dpr{$i}.name = '{$this->escape($field_key)}'
                                        AND dpr{$i}.value = '{$this->escape($field_value)}'";
                }
                $i++;
            }
        }

        // Count rows setting
        if (!isset($params['count_results']) && func_num_args() > 1) {
            $params['count_results'] = true;
        }
        if (!empty($params['custom_select'])) {
            $select = 'SELECT '.$params['custom_select'];
        } elseif (empty($params['count_results'])) {
            $select = "SELECT DISTINCT d.*";
        } elseif ($params['count_results'] === 'only') {
            $select = "SELECT count(*)";
        } else {
            $select = "SELECT SQL_CALC_FOUND_ROWS DISTINCT d.*";
        }

        $group_by = '';
        if (!empty($params['custom_group_by'])) {
            $group_by = "GROUP BY ".$params['custom_group_by'];
        }

        if (is_array($sort)) {
            $sort = join(' '.$order.', ', $sort);
        }

        $order_by = isset($params['count_results']) && $params['count_results'] === 'only' ? '' : 'ORDER BY '.$sort.' '.$order;

        // Fetch data from DB
        $sql = "{$select}
                FROM {$this->table} AS d
                    {$sort_join}
                    {$access_rights_join}
                    {$participants_join}
                    {$markers_join}
                    {$tag_join}
                    {$fields_join}
                WHERE {$access_rights_conditions}
                    AND {$filter_conditions}
                    AND {$participants_condition}
                    AND {$markers_condition}
                {$group_by}
                {$order_by}";
        if ($limit && (empty($params['count_results']) || $params['count_results'] !== 'only')) {
            $sql .= " LIMIT {$offset}, {$limit}";
        }

        // Count rows setting
        $db_result = $this->query($sql);
        if (!empty($params['custom_select'])) {
            return $db_result->fetchAll();
        } elseif (empty($params['count_results'])) {
            return $db_result->fetchAll('id');
        } elseif ($params['count_results'] === 'only') {
            $total_count = $db_result->fetchField();
            return $total_count;
        } else {
            $total_count = $this->query('SELECT FOUND_ROWS()')->fetchField();
            return $db_result->fetchAll('id');
        }
    }

    public function getListByIds($ids)
    {
        if (!$ids || !is_array($ids)) {
            return array();
        }
        $is_visible = wa()->getUser()->isAdmin() ? crmRightConfig::RIGHT_DEAL_ALL : crmRightConfig::RIGHT_DEAL_NONE;
        $deals_list = $this
            ->select("*, '$is_visible' AS `is_visible`")
            ->where("id IN('".join("','", $this->escape($ids, 'int'))."')")
            ->fetchAll('id');

        if (wa()->getUser()->isAdmin()) {
            return $deals_list;
        }

        $contact_ids = array();
        foreach ($deals_list as $id => $deal) {
            $contact_ids[$deal['contact_id']] = intval($deal['contact_id']);
        }

        $contact_model = new crmContactModel();
        $contacts = $contact_model
            ->select('id, crm_vault_id, crm_user_id, create_contact_id')
            ->where('id IN (:ids)', array('ids' => $contact_ids))
            ->fetchAll('id');

        $rights = new crmRights();
        $vault_ids = $rights->getAvailableVaultIds();
        $funnel_rights = $rights->getFunnelRights();

        $dpm = new crmDealParticipantsModel();
        $partisipants = $dpm
            ->select('*')
            ->where("contact_id='".wa()->getUser()->getId()."' AND role_id='USER' AND deal_id IN (:ids)", array('ids' => $ids))
            ->fetchAll();

        foreach ($deals_list as $id => &$deal) {

            if (!empty($contacts[$deal['contact_id']])) {
                $contact = $contacts[$deal['contact_id']];
                $vault_id = isset($contact['crm_vault_id']) ? (int)$contact['crm_vault_id'] : null;
                if (empty($vault_ids[$vault_id])) {
                    $deal['is_visible'] = crmRightConfig::RIGHT_DEAL_NONE;
                    continue;
                }
            }

            $fr = !empty($funnel_rights[$deal['funnel_id']]) ? $funnel_rights[$deal['funnel_id']] : null;
            if ($fr) {
                $deal['is_visible'] = crmRightConfig::RIGHT_FUNNEL_NONE;
                continue;
            }

            // Allow for unassigned users.
            if ($fr == crmRightConfig::RIGHT_FUNNEL_OWN_UNASSIGNED && !$deal['user_contact_id']) {
                $deal['is_visible'] = intval(max($deal['is_visible'], crmRightConfig::RIGHT_FUNNEL_OWN_UNASSIGNED));
            }

            // Allow for user assigned to deal.
            if ($deal['user_contact_id'] == wa()->getUser()->getId()) {
                $deal['is_visible'] = intval(max($deal['is_visible'], crmRightConfig::RIGHT_DEAL_VIEW));
            }
        }
        unset($deal);

        // Allow if user is added as a participant in a deal
        foreach ($partisipants as $p) {
            if (isset($deals_list[$p['deal_id']])) {
                $deals_list[$p['deal_id']]['is_visible'] = intval(max($deals_list[$p['deal_id']]['is_visible'], crmRightConfig::RIGHT_DEAL_VIEW));
            }
        }

        return $deals_list;
    }

    /**
     * @overridden
     * @param int|array[] int $contact_id
     * @return int
     * @throws waDbException
     * @throws waException
     */
    public function getContactLinksCount($contact_id)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (!$contact_ids) {
            return 0;
        }
        $dpm = new crmDealParticipantsModel();
        return parent::getContactLinksCount($contact_ids) + $dpm->getContactLinksCount($contact_ids);
    }

    public function unsetContactLinks($contact_id)
    {
        $contact_ids = crmHelper::toIntArray($contact_id);
        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (!$contact_ids) {
            return;
        }

        // clear user_contact_id relations
        $this->updateByField(['user_contact_id' => $contact_ids], ['user_contact_id' => 0]);

        // leave creator_contact_id links as it
        // with client_id do some work

        // clear links from participants (clients and users)
        $dpm = new crmDealParticipantsModel();
        $dpm->deleteContactLinks($contact_ids, array('contact_id'));

        // found deals with main clients
        $deals = $this->getByField(array(
            'contact_id' => $contact_ids
        ), 'id');

        // deals not found - nothing work with
        if (!$deals) {
            return;
        }

        // search in client participants and change main client (deal.contact_id) to first in participant list
        if ($deals) {
            $deal_ids = array_keys($deals);
            $clients = $dpm->getDealClients($deal_ids);

            foreach ($clients as $deal_id => $deal_clients) {

                // not unset link in this case
                if (empty($deal_clients)) {
                    continue;
                }

                $deal = $deals[$deal_id];
                $main_deal_client_id = $deal['contact_id'];
                if (!isset($deal_clients[$main_deal_client_id])) {
                    // change main client id if there are another leaved clients
                    $deal_client_record = reset($deal_clients);
                    $new_main_client_id = $deal_client_record['contact_id'];
                    $this->updateById($deal_id, array(
                        'contact_id' => $new_main_client_id
                    ));
                }
            }
        }
    }

    public static function getStatusName($status)
    {
        if ($status === self::STATUS_OPEN) {
            return _w('Open');
        } elseif ($status === self::STATUS_WON) {
            return _w('Won');
        } elseif ($status === self::STATUS_LOST) {
            return _w('Lost');
        }
        return '';
    }

    public static function getAllStatuses($with_names = false)
    {
        $statuses = array(self::STATUS_OPEN, self::STATUS_WON, self::STATUS_LOST);
        if (!$with_names) {
            return $statuses;
        }
        $map = array();
        foreach ($statuses as $status) {
            $map[$status] = self::getStatusName($status);
        }
        return $map;
    }

    public function getWonChart($chart_params, $type, $funnels)
    {
        $inner_join = '';
        $condition = '';
        if ($chart_params['user_id'] != 'all') {
            $condition .= ' AND user_contact_id = '.(int)$chart_params['user_id'];
        }
        if ($chart_params['funnel_id'] != 'all') {
            $condition .= ' AND funnel_id = '.(int)$chart_params['funnel_id'];
        }
        if ($chart_params['group_by'] == 'months') {
            $select = "DATE_FORMAT(closed_datetime, '%Y-%m-01') d";
            $group_by = "DATE_FORMAT(closed_datetime, '%Y-%m-01')"; // YEAR(closed_datetime), MONTH(closed_datetime)
            $step = '+1 month';
        } else {
            $select = "DATE(closed_datetime) d";
            $group_by = "DATE(closed_datetime)";
            $step = '+1 day';
        }
        if ($chart_params['funnel_id'] == 'all') {
            $group_by .= ", funnel_id";
        }
        $val = $type != 'sum' ? 'COUNT(*)' : "SUM(amount * currency_rate)";

        if ($chart_params['funnel_id'] == 'all') {
            $val = "funnel_id, ".$val;
        }

        if (!empty($chart_params['active_tag']['id'])) {
            $condition .= " AND tag_id = " . intval($chart_params['active_tag']['id']);
            $inner_join .= "INNER JOIN crm_contact_tags ON -crm_contact_tags.contact_id = crm_deal.id";
        }
        if (!empty($chart_params['active_fields'])) {
            $i = 0;
            foreach ($chart_params['active_fields'] as $field_key => $field_value) {
                $inner_join .= " INNER JOIN crm_deal_params dpr{$i} ON dpr{$i}.deal_id = crm_deal.id
                                AND dpr{$i}.name = '{$this->escape($field_key)}'
                                AND dpr{$i}.value = '{$this->escape($field_value)}'";
                $i++;
            }
        }

        $sql = "SELECT $select, $val cnt FROM {$this->getTableName()} {$inner_join}
                WHERE closed_datetime >= '".$this->escape($chart_params['start_date'])
                ."' AND closed_datetime <= '".$this->escape($chart_params['end_date'])
                ."' AND status_id = 'WON' {$condition}
                GROUP BY $group_by ORDER BY $group_by";

        $res = $this->query($sql)->fetchAll();

        if ($chart_params['group_by'] == 'months') {
            $chart_params['start_date'] = date('Y-m-01', strtotime($chart_params['start_date']));
            $chart_params['end_date'] = date('Y-m-01', strtotime($chart_params['end_date']));
        }

        if ($chart_params['funnel_id'] != 'all') {
            $funnels = array(
                $chart_params['funnel_id'] => array(
                    'name'  => ifset($funnels[$chart_params['funnel_id']]['name']),
                    'color' => ifset($funnels[$chart_params['funnel_id']]['color']),
                )
            );
        }
        unset($funnels['all']);

        $chart = array();

        foreach ($funnels as $id => $f) {
            $points = array();
            $d = $chart_params['start_date'];
            while ($d <= $chart_params['end_date']) {
                $val = 0;
                foreach ($res as $l) {
                    if ($l['d'] == $d && ($chart_params['funnel_id'] != 'all' || $l['funnel_id'] == $id)) {
                        $val = $l['cnt'];
                    }
                }
                $points[] = array(
                    'date'  => $d,
                    'value' => floatval($val),
                );
                $d = date('Y-m-d', strtotime($step, strtotime($d)));
            }
            $chart[] = array(
                'name'  => $f['name'],
                'color' => $f['color'],
                'data'  => $points,
            );
        }
        return $chart;
    }

    protected function getConditions($conditions, &$condition, &$inner_join)
    {
        if ($conditions['user_id'] != 'all') {
            $condition .= ' AND d.user_contact_id = ' . (int)$conditions['user_id'];
        }
        if ($conditions['funnel_id'] != 'all') {
            $condition .= ' AND d.funnel_id = ' . (int)$conditions['funnel_id'];
        }
        if (!empty($conditions['active_tag'])) {
            $condition .= ' AND ct.tag_id = ' . intval($conditions['active_tag']);
            $inner_join = " INNER JOIN crm_contact_tags ct ON -ct.contact_id = d.id";
        }
        if (!empty($conditions['active_fields'])) {
            $i = 0;
            foreach ($conditions['active_fields'] as $field_key => $field_value) {
                $inner_join .= " INNER JOIN crm_deal_params dpr{$i} ON dpr{$i}.deal_id = d.id
                                AND dpr{$i}.name = '{$this->escape($field_key)}'
                                AND dpr{$i}.value = '{$this->escape($field_value)}'";
                $i++;
            }
        }
    }

    public function getLostDeals($conditions)
    {
        $inner_join = $condition = '';

        $this->getConditions($conditions, $condition, $inner_join);

        $sql = "SELECT d.stage_id, COUNT(*) cnt FROM {$this->getTableName()} d {$inner_join}
                WHERE d.status_id = 'LOST'
                  AND d.closed_datetime >= '" . $this->escape($conditions['start_date']) . " 00:00:00'
                  AND d.closed_datetime <= '" . $this->escape($conditions['end_date']) . " 23:59:59' " . $condition . "
                GROUP BY stage_id";

        return $this->query($sql)->fetchAll();
    }

    public function getClosedDeals($conditions)
    {
        $inner_join = $condition = '';

        $this->getConditions($conditions, $condition, $inner_join);

        $sql = "SELECT COUNT(*) cnt FROM {$this->getTableName()} d {$inner_join}
                WHERE d.closed_datetime >= '" . $this->escape($conditions['start_date']) . " 00:00:00'
                  AND d.closed_datetime <= '" . $this->escape($conditions['end_date']) . " 23:59:59'
                  AND status_id != 'OPEN' " . $condition;

        return $this->query($sql)->fetchField('cnt');
    }

    public function getDealsReasons($conditions)
    {
        $condition = $inner_join = '';

        $this->getConditions($conditions, $condition, $inner_join);

        $sql = "SELECT COUNT(*) cnt, dl.name, dl.id, d.lost_id FROM crm_deal d
                  LEFT JOIN crm_deal_lost dl ON dl.id = d.lost_id {$inner_join}
                WHERE d.status_id = 'LOST'
                    AND d.closed_datetime >= '" . $this->escape($conditions['start_date']) . " 00:00:00'
                    AND d.closed_datetime <= '" . $this->escape($conditions['end_date']) . " 23:59:59' {$condition}
                GROUP BY d.lost_id
                ORDER BY cnt DESC";

        return $this->query($sql)->fetchAll();
    }

    public function getWonDealsStat($conditions)
    {
        $condition = " d.status_id = 'WON'";
        $inner_join = '';

        $this->getConditions($conditions, $condition, $inner_join);

        if ($conditions['start_date']) {
            $condition .= " AND d.closed_datetime >= '" . $this->escape($conditions['start_date']) . " 00:00:00'";
        }
        if ($conditions['end_date']) {
            $condition .= " AND d.closed_datetime <= '" . $this->escape($conditions['end_date']) . " 23:59:59'";
        }

        $sql = "SELECT COUNT(*) cnt, SUM(d.amount * d.currency_rate) am FROM {$this->getTableName()} d {$inner_join}
                WHERE {$condition}";

        $res = $this->query($sql)->fetchAssoc();

        $stat = array('count' => ifset($res['cnt']), 'amount' => ifset($res['am']), 'currency_id' => wa()->getSetting('currency'));

        return $stat;
    }

    public function getTimeAvg($chart_params, $threshold = 1)
    {
        $condition = "funnel_id = ".intval($chart_params['funnel_id']);
        if ($chart_params['user_id'] != 'all') {
            $condition .= ' AND user_contact_id = '.(int)$chart_params['user_id'];
        }
        $sql = "SELECT AVG(DATEDIFF(closed_datetime, create_datetime)) sec FROM {$this->getTableName()}
            WHERE $condition AND closed_datetime >= '".$this->escape($chart_params['start_date'])
            ."' AND closed_datetime <= '".$this->escape($chart_params['end_date'])."'";

        return round($this->query($sql)->fetchField('sec'));
    }

    public function getClosedCount($chart_params)
    {
        $condition = "funnel_id = ".intval($chart_params['funnel_id']);
        if ($chart_params['user_id'] != 'all') {
            $condition .= ' AND user_contact_id = '.(int)$chart_params['user_id'];
        }
        $sql = "SELECT COUNT(id) cnt FROM {$this->getTableName()}
            WHERE $condition AND DATE(closed_datetime) >= '".$this->escape($chart_params['start_date'])
            ."' AND DATE(closed_datetime) <= '".$this->escape($chart_params['end_date'])."'";

        return intval($this->query($sql)->fetchField('cnt'));
    }

    // Count number of open deals: by funnel and by stage.
    // This method takes into account access rights of current user logged in.
    // Caches the DB query result in memory and is safe to call multiple times with different arguments.
    public function countOpen($conditions = [], $whith_amount = false)
    {
        static $data = null;
        if ($data === null) {
            // Count all open deals by funnel and stage, checking access rights
            $data = $this->getList([
                'status_id'       => 'OPEN',
                'custom_select'   => 'd.funnel_id, d.stage_id, d.status_id, d.user_contact_id, COUNT(*) AS count' . 
                                    ($whith_amount ? ', SUM(d.amount * d.currency_rate) AS amnt' : ''),
                'custom_group_by' => 'd.funnel_id, d.stage_id, d.status_id, d.user_contact_id',
                'check_rights'    => true,
                'sort'            => 'funnel_id',
            ]);
        }

        $result = 0;
        $total = 0;
        foreach ($data as $row) {
            foreach ($conditions as $col => $value) {
                if ($value != $row[$col]) {
                    continue 2;
                }
            }
            $result += $row['count'];
            if ($whith_amount) {
                $total += $row['amnt'];
            }
        }
        return $whith_amount ? [$result, $total] : $result;
    }

    /**
     * Count number of deals by funnel stage
     * This method does not check access rights and therefore only accurate for app admin.
     * @param int[] $funnel_id
     * @param null|string|string[] $status_id - list of statuses to count (or null if count all statuses)
     *      For available status values see STATUS_* constants
     * @return array
     */
    public function countByStages($funnel_id, $status_id = null)
    {
        if (!$funnel_id) {
            return array();
        }

        // collect where conditions
        $where = array(
            "funnel_id IN (:funnel_id)"
        );

        // prepare condition by statuses
        $status_ids = array();
        if ($status_id !== null) {
            $status_ids = crmHelper::toStrArray($status_id);
            if (!empty($status_ids)) {
                $where[] = "status_id IN(:status_ids)";
            } else {
                $where = array('0');
            }
        }

        // join conditions
        $where = join(" AND ", $where);

        $sql = "SELECT stage_id, count(*) AS count
                FROM {$this->table}
                WHERE {$where}
                GROUP BY stage_id";

        return $this->query($sql, array(
            'funnel_id'  => $funnel_id,
            'status_ids' => $status_ids
        ))->fetchAll('stage_id', true);
    }

    /**
     * @param int|array $contact_id
     * @return array
     */
    public function getOpenDeals($contact_id)
    {
        if (empty($contact_id)) {
            return array();
        }

        $contact_filter = "p.contact_id = ?";
        if (is_array($contact_id)) {
            $contact_filter = "p.contact_id IN (?)";
            $contact_id = array($contact_id);
        }

        $pm = $this->getParticipantsModel();
        $sql = "SELECT d.* FROM {$this->getTableName()} d
            INNER JOIN {$pm->getTableName()} p ON p.deal_id = d.id
            WHERE {$contact_filter} AND p.role_id = 'CLIENT' AND d.status_id = 'OPEN' AND d.external_id IS NULL
            ORDER BY id DESC";
        return $this->query($sql, $contact_id)->fetchAll();
    }

    public function workup($deal)
    {
        if (!empty($deal['id'])) {
            $sql = "SELECT d.*, f.name funnel_name, fs.name stage_name FROM {$this->getTableName()} d
            INNER JOIN crm_funnel f ON f.id = d.funnel_id
            INNER JOIN crm_funnel_stage fs ON fs.id = d.stage_id
            WHERE d.id = ?";
            if ($res = $this->query($sql, $deal['id'])->fetchAssoc()) {
                return $res;
            }
        }
        return $deal;
    }

    /**
     * @param $search
     * @param $options
     * @return array
     */
    public function searchDeal($search, $user_contact_id = null)
    {
        try {
            $search = $this->escape($search, 'like');
            $condition = (empty($user_contact_id) ? '1=1' : 'cd.user_contact_id = '.$user_contact_id);

            return $this->query("
                SELECT cd.* FROM {$this->getTableName()} cd
                JOIN wa_contact wc ON cd.contact_id = wc.id 
                WHERE $condition AND cd.name LIKE ? OR wc.name LIKE ?
            ", [
                "%$search%",
                "%$search%"
            ])->fetchAll('id');
        } catch (waException $_exception) {
            return [];
        }
    }
}
