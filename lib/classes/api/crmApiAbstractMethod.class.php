<?php

abstract class crmApiAbstractMethod extends waAPIMethod
{
    use crmBaseHelpersTrait;

    const METHOD_GET    = 'GET';
    const METHOD_POST   = 'POST';
    const METHOD_DELETE = 'DELETE';
    const METHOD_PUT    = 'PUT';
    const METHOD_PATCH  = 'PATCH';

    const USERPIC_SIZE = 32;
    const THUMB_SIZE   = 64;
    protected $request_body = null;

    protected $default_fa_segment_static_icon = 'user-friends';
    protected $default_fa_segment_dynamic_icon = 'filter';

    public function getResponse($internal = false)
    {
        set_error_handler(function ($errno, $err_str, $err_file, $err_line) {
            // https://www.php.net/manual/en/language.operators.errorcontrol.php
            if (error_reporting() !== 0 && error_reporting() !== E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE) {
                waLog::dump([
                    'warning' => $err_str,
                    'code'    => $errno,
                    'file'    => $err_file,
                    'line'    => $err_line,
                    'url'     => waRequest::server('REQUEST_URI')
                ], 'error.log');
            }
        });

        return parent::getResponse($internal);
    }

    protected function readBodyAsJson()
    {
        $body = $this->readBody();
        if ($body) {
            return json_decode($body, true);
        }
        return null;
    }

    protected function readBody()
    {
        if ($this->request_body === null) {
            $this->request_body = '';
            $contents = file_get_contents('php://input');
            if (is_string($contents) && strlen($contents)) {
                $this->request_body = $contents;
            }
        }
        return $this->request_body;
    }

    protected function getContactsMicrolist(array $ids, array $fields = ['id', 'name', 'userpic'], $userpic_size = '32')
    {
        $list = (new waContactModel)->getByField(['id' => $ids], true);
        return $this->prepareContactsList($list, $fields, $userpic_size);
    }

    protected function prepareContactsList($raw_list, $fields, $userpic_size, $force_lfm_name_format = false)
    {
        return array_map(function ($el) use ($fields, $userpic_size, $force_lfm_name_format) {
            if (in_array('name', $fields)) {
                if (trim((string) $el['name']) === '') {
                    $el['name'] = '('._w('no name').')';
                } elseif ($force_lfm_name_format) {
                    if (!empty($el['lastname'])) {
                        $name = [];
                        foreach(['lastname', 'firstname', 'middlename'] as $part) {
                            $_name = trim((string) ifset($el, $part, ''));
                            if ($_name !== '') {
                                $name[] = $_name;
                            }
                        }
                        $el['name'] = trim(implode(' ', $name));
                    }
                } else {
                    $el['name'] = waContactNameField::formatName($el, true);
                }
            }
            $el['userpic'] = $this->getDataResourceUrl(waContact::getPhotoUrl($el['id'], $el['photo'], $userpic_size, $userpic_size, ifset($el['is_company'], false) ? 'company' : 'person', true));

            if (in_array('tags', $fields) && isset($el['tags']) && is_array($el['tags'])) {
                $el['tags'] = $this->prepareTags($el['tags']);
            }
            return $this->filterFields($el, $fields, ['id' => 'integer', 'company_contact_id' => 'integer', 'create_datetime' => 'datetime', 'last_datetime' => 'datetime', 'is_company' => 'boolean', 'is_pinned' => 'boolean', 'is_banned' => 'boolean', 'is_editable' => 'boolean']);
        }, array_values($raw_list));
    }

    protected function getDataResourceUrl($relative_url)
    {
        $cdn = wa()->getCdn($relative_url);
        if ($cdn->count() > 0) {
            return (string)$cdn;
        }
        $host_url = wa()->getConfig()->getHostUrl();
        return rtrim($host_url, '/') . '/' . ltrim($relative_url, '/');
    }

    protected function filterData(array $result_set, array $fields, array $field_types = [])
    {
        return array_map(function ($el) use ($fields, $field_types) {
            return $this->filterFields($el, $fields, $field_types);
        }, array_values($result_set));
    }

    protected function filterFields($data, array $fields, array $field_types = [])
    {
        if (empty($data) || !is_array($data)) {
            return [];
        }
        $result = [];
        foreach (array_keys($data) as $key) {
            if (in_array($key, $fields)) {
                if (!isset($field_types[$key]) || $data[$key] === null) {
                    $result[$key] = $data[$key];
                    continue;
                }
                if ($field_types[$key] === 'integer') {
                    $result[$key] = intval($data[$key]);
                } elseif ($field_types[$key] === 'boolean') {
                    $result[$key] = boolval($data[$key]);
                } elseif ($field_types[$key] === 'float') {
                    $result[$key] = floatval($data[$key]);
                } elseif ($field_types[$key] === 'double') {
                    $result[$key] = doubleval($data[$key]);
                } elseif ($field_types[$key] === 'datetime') {
                    $result[$key] = $this->formatDatetimeToISO8601($data[$key]);
                } else {
                    $result[$key] = $data[$key];
                }
            }
        }
        return $result;
    }

    protected function prepareFiles(array $result_set)
    {
        return array_map(function ($el) {
            return $this->prepareFile($el);
        }, array_values($result_set));
    }

    protected function prepareFile(array $data)
    {
        return $this->filterFields(
            $data,
            ['id', 'name', 'size', 'ext', 'create_datetime', 'comment', 'url'],
            ['id' => 'integer', 'size' => 'integer', 'create_datetime' => 'datetime']
        );
    }

    protected function prepareSource(array $data)
    {
        return $this->filterFields(
            $data,
            ['id', 'type', 'name', 'provider', 'icon_url', 'icon_fab', 'icon_color'],
            ['id' => 'integer']
        );
    }

    protected function prepareDealShort(array $data)
    {
        if (isset($data['funnel'])) {
            if (empty($data['funnel']['icon'])) {
                $data['funnel']['icon'] = 'fas fa-briefcase';
            }
            $data['funnel'] = $this->filterFields($data['funnel'], ['id', 'name', 'color', 'icon'], ['id' => 'integer']);
        }
        if (isset($data['stage'])) {
            $data['stage'] = $this->filterFields($data['stage'], ['id', 'name', 'color'], ['id' => 'integer']);
        }

        return $this->filterFields(
            $data,
            [
                'id',
                'status_id',
                'name',
                'amount',
                'currency_id',
                'contact',
                'funnel',
                'stage'
            ], [
                'id'     => 'integer',
                'amount' => 'float'
            ]
        );
    }

    protected function prepareReminder(array $data)
    {
        if (ifset($data['contact_id']) < 0) {
            $data['deal_id'] = -1 * $data['contact_id'];
            $data['contact_id'] = null;
        } else {
            $data['deal_id'] = null;
        }
        return $this->filterFields(
            $data,
            ['id', 'create_datetime', 'creator_contact_id', 'contact_id', 'deal_id', 'user_contact_id', 'due_date', 'due_datetime', 'complete_datetime', 'content', 'type'],
            ['id' => 'integer', 'creator_contact_id' => 'integer', 'contact_id' => 'integer', 'deal_id' => 'integer', 'user_contact_id' => 'integer', 'create_datetime' => 'datetime', 'due_datetime' => 'datetime', 'complete_datetime' => 'datetime']
        );
    }

    protected function prepareInvoice(array $data)
    {
        $state = crmInvoice::getState($data['state_id']);
        $data['state_name'] = ifset($state, 'name', false) ? $state['name'] : $data['state_id'];
        return $this->filterFields(
            $data,
            ['id', 'create_datetime', 'update_datetime', 'number', 'invoice_date', 'creator_contact_id', 'company_id', 'contact_id', 'due_days', 'due_date', 'amount', 'currency_id', 'currency_rate', 'tax_name', 'tax_percent', 'tax_type', 'tax_amount', 'discount_percent', 'discount_amount', 'summary', 'comment', 'state_id', 'state_name', 'payment_datetime', 'deal_id'],
            ['id' => 'integer', 'creator_contact_id' => 'integer', 'contact_id' => 'integer', 'company_id' => 'integer', 'due_days' => 'integer', 'amount' => 'float', 'currency_rate' => 'float', 'tax_percent' => 'float', 'tax_amount' => 'float', 'discount_percent' => 'float', 'discount_amount' => 'float', 'deal_id' => 'integer', 'create_datetime' => 'datetime', 'update_datetime' => 'datetime', 'payment_datetime' => 'datetime']
        );
    }

    protected function prepareMessage(array $data)
    {
        return $this->filterFields(
            $data,
            [
                'id',
                'create_datetime',
                'creator_contact_id',
                'transport',
                'direction',
                'contact_id',
                'deal_id',
                'source_id',
                'source',
                'subject',
                'body',
                'body_sanitized',
                'body_plain',
                'from',
                'to',
                'original',
                'event',
                'conversation_id',
                'read',
                'can_view',
                'contact',
                'attachments',
                'extras'
            ], [
                'id'                 => 'integer',
                'creator_contact_id' => 'integer',
                'contact_id'         => 'integer',
                'deal_id'            => 'integer',
                'source_id'          => 'integer',
                'original'           => 'boolean',
                'conversation_id'    => 'integer',
                'read'               => 'boolean',
                'can_view'           => 'boolean',
                'create_datetime'    => 'datetime'
            ]
        );
    }

    protected function prepareCall(array $data)
    {
        if (ifset($data['plugin_icon'])) {
            $data['plugin_icon'] = $this->getDataResourceUrl($data['plugin_icon']);
        }
        return $this->filterFields(
            $data,
            ['id', 'direction', 'status_id', 'create_datetime', 'finish_datetime', 'plugin_id', 'plugin_call_id', 'plugin_gateway', 'plugin_user_number', 'plugin_client_number', 'plugin_record_id', 'notification_sent', 'deal_id', 'client_contact_id', 'user_contact_id', 'duration', 'comment', 'has_access', 'plugin_icon', 'plugin_name', 'redirect_allowed', 'client_phone_formatted', 'contact', 'user'],
            ['id' => 'integer', 'client_contact_id' => 'integer', 'user_contact_id' => 'integer', 'deal_id' => 'integer', 'create_datetime' => 'datetime', 'finish_datetime' => 'datetime', 'duration' => 'integer', 'has_access' => 'boolean', 'notification_sent' => 'boolean', 'redirect_allowed' => 'boolean']
        );
    }

    protected function prepareSegments(array $result_set)
    {
        $rights = $this->getCrmRights();
        $result = array_map(function ($el) use ($rights) {
            $el['is_editable'] = $rights->canEditSegment($el);
            return $el;
        }, $result_set);

        $result = $this->filterData(
            $result,
            ['id', 'name', 'icon', 'icon_path', 'type', 'count', 'archived', 'is_editable'],
            ['id' => 'integer', 'count' => 'integer', 'archived' => 'boolean']
        );

        return $this->prepareSegmentIcons($result);
    }

    protected function prepareSegmentIcons(array $segments)
    {
        $fa_segment_icons = crmSegmentModel::getIcons('2.0');
        return array_map(function ($segment) use ($fa_segment_icons) {
            if (empty($segment['icon_path']) && !in_array($segment['icon'], $fa_segment_icons)) {
                $segment['icon'] = ($segment['type'] === 'search') ? $this->default_fa_segment_dynamic_icon : $this->default_fa_segment_static_icon;
            }
            if (!empty($segment['icon_path'])) {
                $segment['icon'] = null;
            }
            return $segment;
        }, $segments);
    }

    protected function prepareTags(array $result_set)
    {
        return $this->filterData(
            $result_set,
            ['id', 'name', 'color', 'bg_color', 'count', 'size', 'opacity'],
            ['id' => 'integer', 'count' => 'integer', 'size' => 'integer', 'opacity' => 'float']
        );
    }

    protected function addFormattedPhoneValues($phone_list)
    {
        if (empty($phone_list) || !is_array($phone_list)) {
            return null;
        }
        $phone_formatter = new waContactPhoneFormatter();
        return array_map(function ($phone) use ($phone_formatter) {
            $phone['data'] = $this->doPhonePrefix($phone['value']);
            $phone['value'] = $phone_formatter->format($phone['value']);
            if (!empty($phone['ext'])) {
                $phone['ext_value'] = _ws($phone['ext']);
            }
            return $phone;
        }, $phone_list);
    }

    protected function addFormattedEmailValues($email_list)
    {
        if (empty($email_list) || !is_array($email_list)) {
            return null;
        }

        return array_map(function ($email) {
            $email['data'] = $email['value'];
            unset($email['email']);
            if (!empty($email['ext'])) {
                $email['ext_value'] = _ws($email['ext']);
            }
            return $email;
        }, $email_list);
    }

    protected function formatDatetimeToISO8601($sql_dt)
    {
        try {
            $dt = new DateTime((string) $sql_dt);
            $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Y-m-d\TH:i:s.u\Z');
        } catch (Exception $ex) {
            return $sql_dt;
        }
    }

    protected function getUser()
    {
        return wa()->getUser();
    }

    public function getApp()
    {
        return $this->getConfig()->getApplication();
    }

    public function getAppId()
    {
        return $this->getApp();
    }

    public function getConfig()
    {
        return wa()->getConfig();
    }

    protected function prepareUserpic($contact, $userpic_size)
    {
        $contact['id'] = intval($contact['id']);
        $contact['userpic'] = $this->getDataResourceUrl(waContact::getPhotoUrl(
            $contact['id'],
            ifset($contact, 'photo', null),
            $userpic_size,
            $userpic_size,
            ifset($contact, 'is_company', false) ? 'company' : 'person',
            true
        ));
        unset($contact['photo']);
        unset($contact['is_company']);
        return $contact;
    }

    /*
    protected function prepareMessageParams($message_params) {
        return array_map(function ($el) use ($message_params) {
            return [
                'name' => $el,
                'value' => $message_params[$el],
            ];
        }, array_keys($message_params));
    }
    */

    protected function prepareMessagesForLog($messages)
    {
        $message_ids = array_column($messages, 'id');
        $sources = array_column($messages, 'source');

        $files = $this->getAttachments($message_ids);
        $messages = array_reduce($messages, function ($result, $el) use ($files) {
            if (isset($files[$el['id']])) {
                $el['attachments'] = $files[$el['id']];
            }
            $result[$el['id']] = $el;
            return $result;
        }, []);


        $source_helpers = array_reduce($sources, function ($result, $el) {
            $result[$el['id']] = crmSourceHelper::factory(crmSource::factory($el['id']));
            return $result;
        }, []);

        foreach ($source_helpers as $source_id => $helper) {
            $source_messages = array_filter($messages, function($m) use ($source_id) {
                return ifset($m['source_id']) == $source_id;
            });
            $res = $helper->normalazeMessagesExtras($source_messages);
            foreach ($res as $m) {
                if (empty($m['body_plain'])) {
                    if (ifset($m['extras'])) {
                        if (ifset($m['extras']['videos'])) {
                            $m['body_plain'] = _w('Video');
                        } elseif (ifset($m['extras']['audios'])) {
                            $m['body_plain'] = _w('Audio');
                        } elseif (ifset($m['extras']['images'])) {
                            $m['body_plain'] = _w('Image');
                        } elseif (ifset($m['extras']['stickers'])) {
                            $m['body_plain'] = _w('Sticker');
                        } elseif (ifset($m['extras']['locations'])) {
                            $m['body_plain'] = _w('Geolocation');
                        }
                    } elseif (ifset($m['attachments'])) {
                        $m['body_plain'] = _w('File');
                    }
                }
                $messages[$m['id']] = $m;
            }
        }
        return $messages;
    }

    protected function prepareLog($log, $conversations, $userpic_size, $deal = null)
    {
        $log = array_filter($log, function ($l) {
            $result = true;
            if ($l['object_type'] === crmLogModel::OBJECT_TYPE_ORDER_LOG && empty($l['order'])) {
                $result = false;
            }
            return $result;
        });

        $funnels = $this->getFunnelModel()->getAllFunnels(true);

        return array_map(function ($l) use ($userpic_size, $conversations, $funnels, $deal) {
            switch ($l['object_type']) {
                case crmLogModel::OBJECT_TYPE_CONTACT:
                    $l['icon'] = [
                        'fa' => 'info',
                        'color' => '#AAAAAA',
                    ];
                    break;
                case crmLogModel::OBJECT_TYPE_DEAL:
                    $fa = 'briefcase';
                    $color = '#275A00';
                    $_deal = isset($l['deal']) ? $l['deal'] : $deal;
                    if (!empty($_deal['funnel_id']) && isset($funnels[$_deal['funnel_id']])) {
                        if (!empty($funnels[$_deal['funnel_id']]['icon'])) {
                            $fa = $funnels[$_deal['funnel_id']]['icon'];
                            $fa = str_replace('fas fa-', '', $fa);
                        }
                        $color = $funnels[$_deal['funnel_id']]['color'] ?: $color;
                    }
                    $l['icon'] = [
                        'fa' => $fa,
                        'color' => $color,
                    ];
                    if (empty($l['object_id']) && isset($l['deal']) && isset($l['deal']['id'])) {
                        $l['object_id'] = $l['deal']['id'];
                    }
                    break;
                case crmLogModel::OBJECT_TYPE_REMINDER:
                    $l['icon'] = [
                        'fa' => 'bell',
                        'color' => '#AAAAAA',
                    ];
                    break;
                case crmLogModel::OBJECT_TYPE_INVOICE:
                    $l['icon'] = [
                        'fa' => 'receipt',
                        'color' => '#F98836',
                    ];
                    break;
                case crmLogModel::OBJECT_TYPE_MESSAGE:
                    $l['icon'] = [
                        'fa' => 'envelope',
                        'color' => '#BB64FF',
                    ];
                    break;
                case crmLogModel::OBJECT_TYPE_NOTE:
                    $l['icon'] = [
                        'fa' => 'sticky-note',
                        'color' => '#EFC61F',
                    ];
                    break;
                case crmLogModel::OBJECT_TYPE_FILE:
                    $l['icon'] = [
                        'fa' => 'paperclip',
                        'color' => '#EFC61F',
                    ];
                    break;
                case crmLogModel::OBJECT_TYPE_CALL:
                    $l['icon'] = [
                        'fa' => 'phone-alt',
                        'color' => '#00CC20',
                    ];
                    break;
                case crmLogModel::OBJECT_TYPE_ORDER_LOG:
                    $l['icon'] = [
                        'url' => wa()->getAppStaticUrl('shop', true) . 'img/shop.png',
                    ];
                    if (isset($l['order_log_item'])) {
                        if (!ifset($l['order_log_item']['text'])) {
                            $l['order_log_item']['text'] = $l['action_name'];
                        }
                        $l['order_log_item'] = $this->filterFields($l['order_log_item'], ['id', 'order_id', 'contact_id', 'action_id', 'text', 'datetime', 'before_state_id', 'after_state_id'] , ['id' => 'integer', 'order_id' => 'integer', 'contact_id' => 'integer', 'datetime' => 'datetime']);
                    }
                    if (isset($l['order'])) {
                        $l['order'] = $this->filterFields($l['order'], ['id', 'contact_id', 'create_datetime', 'update_datetime', 'paid_datetime', 'shipping_datetime', 'state_id', 'total', 'currency', 'rate', 'tax', 'shipping', 'discount', 'assigned_contact_id', 'number', 'comment', 'contact'] , ['id' => 'integer', 'contact_id' => 'integer', 'assigned_contact_id' => 'integer', 'create_datetime' => 'datetime', 'update_datetime' => 'datetime', 'paid_datetime' => 'datetime', 'shipping_datetime' => 'datetime', 'total' => 'float','rate' => 'float', 'tax' => 'float', 'shipping' => 'float', 'discount' => 'float']);
                        if (isset($l['order']['contact'])) {
                            $l['order']['contact'] = $this->prepareUserpic($l['order']['contact'], $userpic_size);
                        }
                        $l['object_id'] = $l['order']['id'];
                        $l['object_type'] = crmLogModel::OBJECT_TYPE_ORDER;
                    }
                    break;
            }

            if (isset($l['create_app_id'])) {
                $l['icon'] = $this->getAppIcon($l['create_app_id']);
            }

            if ($l['action'] == 'contact_ban') {
                $l['icon'] = [
                    'fa' => 'ban',
                    'color' => 'red',
                ];
            }

            if (isset($l['deal'])) {
                $l['deal'] = $this->prepareDealShort($l['deal']);
            }
            if (isset($l['reminder'])) {
                $l['reminder'] = $this->prepareReminder($l['reminder']);
            }
            if (isset($l['invoice'])) {
                $l['invoice'] = $this->prepareInvoice($l['invoice']);
            }
            if (isset($l['file'])) {
                $l['file'] = $this->prepareFile($l['file']);
            }
            if (isset($l['actor'])) {
                $l['actor'] = $this->prepareUserpic($l['actor'], $userpic_size);
            }
            if (isset($l['contact'])) {
                $l['contact'] = $this->prepareUserpic($l['contact'], $userpic_size);
            }
            if (isset($l['message'])) {
                $l['message'] = $this->prepareMessage($l['message']);
                if (isset($l['message']['contact'])) {
                    $l['message']['contact'] = $this->prepareUserpic($l['message']['contact'], $userpic_size);
                }
                if (isset($l['message']['source'])) {
                    $l['message']['source'] = $this->prepareSource($l['message']['source']);
                    if (ifset($l['message']['source']['icon_fab'])) {
                        unset($l['icon']['fa']);
                        $l['icon']['fab'] = $l['message']['source']['icon_fab'];
                        if (ifset($l['message']['source']['icon_color'])) {
                            $l['icon']['color'] = $l['message']['source']['icon_color'];
                        }
                    } elseif (ifset($l['message']['source']['icon_url'])) {
                        $l['icon'] = [
                            'url' => $l['message']['source']['icon_url'],
                        ];
                    }
                }
                /*
                if (ifset($l['message']['params'])) {
                    $l['message']['params'] = $this->prepareMessageParams($l['message']['params']);
                }
                */
                if (ifset($l['message']['conversation_id']) && isset($conversations[$l['message']['conversation_id']])) {
                    $l['message']['conversation_count'] = intval($conversations[$l['message']['conversation_id']]['count']);
                    $l['message']['conversation_participants'] = array_map(function ($_part) use ($userpic_size) {
                        return $this->prepareUserpic($_part, $userpic_size);
                    }, ifset($conversations, $l['message']['conversation_id'], 'participants', []));
                }
                if (!ifset($l['message']['can_view'])) {
                    $l['message']['body'] = '...';
                    $l['message']['body_plain'] = '...';
                    $l['message']['body_sanitized'] = '...';
                }
            }
            if (isset($l['call'])) {
                if (isset($l['call']['contact'])) {
                    $l['call']['contact'] = $this->prepareUserpic($l['call']['contact'], $userpic_size);
                }
                if (isset($l['call']['user'])) {
                    $l['call']['user'] = $this->prepareUserpic($l['call']['user'], $userpic_size);
                }
                $l['call'] = $this->prepareCall($l['call']);
            }

            if (ifset($l['is_not_available'])) {
                $l['object_id'] = null;
                $l['object_type'] = crmLogModel::OBJECT_TYPE_CONTACT;
            }
            return $l;
        }, $log);
    }

    protected function normalizeFieldType($raw_type)
    {
        switch ($raw_type) {
            case 'Number':
                return 'number';
            case 'Select':
            case 'Country':
                return 'select';
            case 'Radio':
                return 'radio';
            case 'Checkbox':
                return 'checkbox';
            case 'Hidden':
                return 'hidden';
            case 'Text':
                return 'text';
            case 'Address':
                return 'address';
            case 'Birthday':
            case 'Composite':
                return 'composite';
            case 'Date':
                return 'date';
            default:
                return 'string';
        }
    }

    protected function getAttachments($message_ids)
    {
        if (empty($message_ids)) {
            return [];
        }
        $attachments = (new crmMessageAttachmentsModel)->getByField(['message_id' => $message_ids], 'file_id');
        $file_ids = array_keys($attachments);
        $files = $this->getFileModel()->getFiles($file_ids);
        $app_url = rtrim(wa()->getRootUrl(true), '/').'/'.wa()->getConfig()->getBackendUrl().'/crm/';

        return array_reduce($attachments, function ($res, $el) use ($files, $app_url) {
            $file = ifset($files, $el['file_id'], null);
            if (!$file) {
                return $res;
            }
            if (!isset($res[$el['message_id']])) {
                $res[$el['message_id']] = [];
            }
            $file['url'] = $app_url.'?module=file&action=download&id='.$file['id'];
            $res[$el['message_id']][] = $this->prepareFile($file);
            return $res;
        }, []);
    }

    protected function getUrlMap($address, $longitude, $latitude)
    {
        static $map_adapter = null;

        if ($map_adapter === false) {
            return null;
        } elseif ($map_adapter === null) {
            try {
                $model = new waAppSettingsModel();
                $_adapter = $model->get('webasyst', 'backend_map_adapter', 'google');

                /** @var waMapAdapter $map_adapter */
                $map_adapter = wa()->getMap($_adapter);
            } catch (waException $exception) {
                $map_adapter = false;
                return null;
            }
        }

        return $map_adapter->getUrlToMap($address, $longitude, $latitude, 15);
    }

    protected function getAppIcon($app)
    {
        if (!is_array($app)) {
            $app = wa()->getAppInfo($app);
        }

        $icon_data = ifset($app, 'icon', null);
        if (empty($icon_data)) {
            return [
                'fa' => 'info',
                'color' => '#AAAAAA',
            ];
        }
        $sizes = array_keys($icon_data);
        $sizes = array_filter($sizes, function ($el) {
            return $el > 40;
        });
        if (empty($sizes)) {
            return [
                'fa' => 'info',
                'color' => '#AAAAAA',
            ];
        }
        $size = min($sizes);
        return [
            'url' => rtrim(wa()->getRootUrl(true), '/') . '/' . $icon_data[$size],
        ];
    }

    protected function doPhonePrefix($phone_number)
    {
        if (empty($phone_number)) {
            return $phone_number;
        }
        $phone_prefix = wa('crm')->getConfig()->getPhoneTransformPrefix();
        if (!empty($phone_prefix['input_code']) && strpos($phone_number, $phone_prefix['input_code']) === 0) {
            return $phone_number;
        }
        $phone_number = ltrim($phone_number, '+');
        return '+'.$phone_number;
    }
}
