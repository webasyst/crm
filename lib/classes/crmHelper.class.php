<?php

class crmHelper
{
    protected static $wa_groups = array();
    protected static $exts;

    /**
     * @return array
     */
    public static function getWaGroups()
    {
        if (!self::$wa_groups) {
            $gm = new waGroupModel();
            self::$wa_groups = $gm->select('*')->order('sort')->fetchAll('id');
        }
        return self::$wa_groups;
    }

    /**
     * "...whether I am a trembling creature or whether I have the right..."
     *
     * With an argument: asks for particular access right.
     * No arguments: asks for full access to application.
     */
    public static function hasRights($right = null)
    {
        if ($right) {
            return wa()->getUser()->getRights(wa()->getApp(), $right);
        } else {
            return wa()->getUser()->isAdmin(wa()->getApp());
        }
    }

    /**
     * @param null|string|array $replace
     * @param null|string $value
     * @return mixed|string
     *
     */
    public static function getUrl($replace = null, $value = null)
    {
        $url = parse_url(waRequest::server('REQUEST_URI'));
        $url = $url['path'];
        $get = waRequest::get();
        if (is_string($replace)) {
            $replace = array($replace => $value);
        }
        $replace = (array)$replace;
        $replace['_'] = null;
        foreach ($replace as $key => $value) {
            if ($key === null) {
                continue;
            }
            if ($value !== null) {
                $get[$key] = $value;
            } else if (substr($key, 0, 1) == "/") {
                foreach ($get as $get_key => $get_value) {
                    if (preg_match($key, $get_key)) {
                        unset($get[$get_key]);
                    }
                }
            } else {
                unset($get[$key]);
            }
        }

        if ($get) {
            $url .= '?'.http_build_query($get);
        }
        return $url;
    }

    /**
     * @param array $params
     * @param null $id
     * @param string $style
     * @return string
     */
    public static function paginator($params = array(), $id = null, $style = '')
    {
        $default_params = array(
            'count'            => 30,
            'offset'           => 0,
            'total_count_text' => '',
            'total_count'      => 30
        );
        $params = array_merge($default_params, $params);
        $id = $id ? $id : ('paginator-'.rand());

        $html = '';

        $type = wa('crm')->getConfig()->getOption('paginator_type');

        $html .= "<div class='block paging contact-paginator' id='{$id}' style='{$style}'>";
        if ($params['total_count'] > 30) {
            $loc_str = _w('Show %s records on a page');
            $select_html = "  <select class='items-per-page'>";
            foreach (array(30, 50, 100, 200, 500) as $n) {
                $select_html .= "  <option value='{$n}' ".($params['count'] == $n ? 'selected' : '').">{$n}</option>";
            }
            $select_html .= "  </select> ";
            $html .= "<span class='c-page-num'>".sprintf($loc_str, $select_html)."</span>";
        }
        if ($type === 'page') {
            $html .= "<span>{$params['total_count_text']} <span class='total'>{$params['total_count']}</span></span>";
        }
        $pages = ceil($params['total_count'] / $params['count']);
        if ($pages > 1) {
            $html .= '<span class="pages">';
            if ($type === 'page') {
                $html .= _w('Pages').': ';
            }
            $p = ceil($params['offset'] / $params['count']) + 1;

            if ($type === 'page') {
                $f = 0;
                for ($i = 1; $i < $pages; $i += 1) {
                    if (abs($p - $i) < 2 || $i < 2 || $pages - $i < 1) {
                        $html .= "<a class='".($i == $p ? 'selected' : '')."' href='javascript:void(0);' data-offset='".(($i - 1) * $params['count'])."'>{$i}</a>";
                        $f = 0;
                    } else {
                        if ($f++ < 3) {
                            $html .= '.';
                        }
                    }
                }
            } else {
                $html .= ($params['offset'] + 1).'&mdash;'.(min($params['total_count'], $params['offset'] + $params['count']));
                $html .= ' '._w('of').' '.$params['total_count'];
            }

            if ($p > 1) {
                $html .= "<a href='javascript:void(0);' data-offset='".(($p - 2) * $params['count'])."' class='prevnext'><i class='icon10 larr'></i>"._w('prev')."</a>";
            }
            if ($p < $pages) {
                $html .= "<a href='javascript:void(0);' data-offset='".($p * $params['count'])."' class='prevnext'>"._w('next')."<i class='icon10 rarr'></i></a>";
            }
            $html .= '</span>';
        } else {
            if ($type !== 'page') {
                $html .= min($params['offset'] + 1, $params['total_count']).'&mdash;'.(min($params['total_count'], $params['offset'] + $params['count']));
                $html .= ' '._w('of').' '.$params['total_count'];
            }
        }
        $html .= '</div>';

        $html .= "<script>";
        $html .= "$(function() {";
        $html .= "$('#{$id}').off('click.contact_paginator', '.pages a').on('click.contact_paginator', '.pages a', function() {";
        $html .= "$('#{$id}').trigger('choose_page', [$(this).data('offset')]);";
        $html .= "});";
        $html .= "});";
        $html .= "</script>";

        return $html;
    }

    public static function suggestName($contact)
    {
        if (empty($contact['name'])) {
            $contact['name'] = waContactNameField::formatName($contact, true);
        }
        $str = (!empty($contact['company']) ? $contact['company'] : $contact['name']);
        if (strlen($str) > 0) {
            return sprintf(_w('%s deal'), $str);
        }
        return '';
    }

    /**
     * Cast to array of integers
     * @param mixed $val
     * @return int[]
     */
    public static function toIntArray($val)
    {
        $callback = 'return is_scalar($i) ? intval($i) : 0;';
        $callback = wa_lambda('$i', $callback);
        if (!is_scalar($val) && !is_array($val)) {
            $val = array();
        }
        return array_map($callback, (array)$val);
    }

    /**
     * Cast to array of strings
     * @param mixed $val
     * @param bool $trim
     * @return string[]
     */
    public static function toStrArray($val, $trim = true)
    {
        $callback = 'return is_scalar($s) ? strval($s) : "";';
        if ($trim === true) {
            $callback = 'return is_scalar($s) ? trim(strval($s)) : "";';
        }
        $callback = wa_lambda('$s', $callback);
        if (!is_scalar($val) && !is_array($val)) {
            $val = array();
        }
        return array_map($callback, (array)$val);
    }

    /**
     * Drop all not positive values from input array
     * @param array [int] $int_array
     * @return array[int]
     */
    public static function dropNotPositive($int_array)
    {
        foreach ($int_array as $index => $int) {
            if ($int <= 0) {
                unset($int_array[$index]);
            }
        }
        return $int_array;
    }

    /**
     * @deprecated
     * This method not-deprecated up to version 1.0.7
     * use crmHelper::getFileFolderPath() instead
     * @param $id
     * @param bool $create
     * @return string
     */
    public static function getFilePath($id, $create = false)
    {
        return self::getFileFolderPath($id, $create);
    }

    /**
     * @param $id
     * @param bool $create
     * @return string
     */
    public static function getFileFolderPath($id, $create = false)
    {
        return wa()->getDataPath(self::getFileFolder($id), false, 'crm', $create);
    }

    /**
     * @param $id
     * @return string
     */
    public static function getFileFolder($id)
    {
        $str = str_pad($id, 4, '0', STR_PAD_LEFT);
        return 'files/'.substr($str, -2).'/'.substr($str, -4, 2).'/';
    }

    public static function getReminderState($reminder, $ignore_completed = false)
    {
        if ($reminder['complete_datetime'] && !$ignore_completed) {
            $reminder['state'] = 'completed';
        } elseif ($reminder['due_date'] < date('Y-m-d') || ($reminder['due_datetime'] && $reminder['due_datetime'] < date('Y-m-d H:i:s'))) {
            $reminder['state'] = 'overdue';
        } elseif ($reminder['due_date'] == date('Y-m-d') && (!$reminder['due_datetime'] || $reminder['due_datetime'] >= date('Y-m-d H:i:s'))) {
            $reminder['state'] = 'burn';
        } elseif ($reminder['due_date'] == date('Y-m-d', strtotime('+1 day'))) {
            $reminder['state'] = 'actual';
        } else {
            $reminder['state'] = 'normal';
        }
        return $reminder['state'];
    }

    public static function getDealReminderState($reminder_datetime)
    {
        $reminder_state = null;
        if ($reminder_datetime) {
            $reminder = array(
                'complete_datetime' => null,
                'due_date'          => date('Y-m-d', strtotime($reminder_datetime)),
                'due_datetime'      => $reminder_datetime,
            );
            $reminder_state = self::getReminderState($reminder);
        }
        return $reminder_state;
    }

    public static function getReminderTitle($reminder_state, $reminder_datetime)
    {
        if ($reminder_state == 'overdue') {
            $title = _w('Overdue reminder');
        } elseif ($reminder_state == 'burn') {
            $title = _w('Reminder due today');
        } elseif ($reminder_datetime) { // $reminder_state == 'actual' || $reminder_state == 'normal'
            $title = sprintf_wp('Reminder due to %s', waDateTime::format('date', $reminder_datetime));
        } else {
            $title = null;
        }
        return $title;
    }

    /**
     * Convert file size to formatted string
     * @param int $file_size
     * @return string
     */
    public static function formatFileSize($file_size)
    {
        $_kb = 1024;
        $_mb = 1024 * $_kb;
        if ($file_size <= $_kb) {
            $file_size = $file_size._w(' B');
        } else {
            if ($file_size > $_kb && $file_size < $_mb) {
                $file_size = round($file_size / $_kb)._w(' KB');
            } else {
                $file_size = round($file_size / $_mb, 1)._w(' MB');
            }
        }
        return $file_size;
    }

    /**
     * Get info by file extension
     * @param string $ext
     * @return array
     */
    public static function getExtInfo($ext)
    {
        if (self::$exts === null) {
            self::$exts = wa('crm')->getConfig()->getExts();
        }
        $ext = strtolower($ext);
        $info = ifset(self::$exts[$ext], self::$exts['__file__']);
        $ext_img_url = wa('crm')->getConfig()->getExtImgUrl();
        if ($info['img'][0] !== '/') {
            $info['img'] = $ext_img_url.$info['img'];
        }
        $info['id'] = $ext;

        return $info;
    }

    /**
     * Returns HTML code of a Webasyst icon.
     *
     * @param string|null $icon Icon type
     * @param string|null $default Default icon type to be used if $icon is empty.
     * @param int $size Icon size in pixels. Available sizes: 10, 16.
     * @param array $params Extra parameters:
     *     'class' => class name tp be added to icon's HTML code
     * @return string
     */
    public static function getIcon($icon, $default = null, $size = 16, $params = array())
    {
        if (!$icon && $default) {
            $icon = $default;
        }
        $class = isset($params['class']) ? ' '.htmlentities($params['class'], ENT_QUOTES, 'utf-8') : '';

        if ($icon) {
            if (preg_match('/^icon\.([\d\w_\-]+)$/', $icon, $matches)) {
                $size = ($size == 16) ? 16 : 10;
                $icon = "<i class='icon{$size} {$matches[1]}{$class}'></i>";
            } elseif (preg_match('@[\\/]+@', $icon)) {
                $size = max(10, min(16, $size));
                $icon = "<i class='icon{$size} {$class}' style='background: url({$icon})'></i>";
            } else {
                $size = ($size == 16) ? 16 : 10;
                $icon = "<i class='icon{$size} {$icon}{$class}'></i>";
            }
        }
        return $icon;
    }

    /**
     * json_decode has problems with big integers in some platforms.
     * @see discussion here http://stackoverflow.com/questions/19520487/json-bigint-as-string-removed-in-php-5-5
     * @param $input
     * @param bool $assoc
     * @return mixed
     */
    public static function jsonDecode($input, $assoc = false)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '>=') && !(defined('JSON_C_VERSION') && PHP_INT_SIZE > 4)) {
            /** In PHP >=5.4.0, json_decode() accepts an options parameter, that allows you
             * to specify that large ints (like Steam Transaction IDs) should be treated as
             * strings, rather than the PHP default behaviour of converting them to floats.
             */
            $obj = json_decode($input, $assoc, 512, JSON_BIGINT_AS_STRING);
        } else {
            /** Not all servers will support that, however, so for older versions we must
             * manually detect large ints in the JSON string and quote them (thus converting
             *them to strings) before decoding, hence the preg_replace() call.
             */
            $max_int_length = strlen((string)PHP_INT_MAX) - 1;
            $json_without_bigints = preg_replace('/(:|,|\[|^)\s*(-?\d{'.$max_int_length.',})/', '$1"$2"', $input);
            $obj = json_decode($json_without_bigints, $assoc);
        }
        return $obj;
    }

    public static function getInvoiceHash($invoice)
    {
        self::isTemplate();
        $md5 = md5($invoice['id'].$invoice['create_datetime']);
        return substr($md5, 0, 16).$invoice['id'].substr($md5, -16);
    }

    public static function renderViewAction($action)
    {
        if (is_string($action) && class_exists($action)) {
            $action = new $action();
        }
        if (!($action instanceof waViewAction)) {
            return null;
        }
        $view = wa()->getView();
        $vars = $view->getVars();
        $html = $action->display();
        $view->clearAllAssign();
        $view->assign($vars);
        return $html;
    }

    public static function getImportExportEncodings()
    {
        $encoding = mb_list_encodings();
        $list = array();
        foreach ($encoding as $k => $v) {
            if ($k > 10) {
                $list[strtolower($v)] = $v;
            }
        }
        natcasesort($list);
        return $list;
    }

    public static function getFunnelStageColors($funnels, &$stages)
    {
        foreach ($stages as $s) {
            $funnel_stages[$s['funnel_id']][$s['id']] = $s;
        }
        foreach ($funnels as $fid => $f) {
            $i = 0;
            foreach ($stages as &$s) {
                if ($s['funnel_id'] != $fid) {
                    continue;
                }
                $s['color'] = crmFunnel::getFunnelStageColor($f['open_color'], $f['close_color'], $i, count($funnel_stages[$s['funnel_id']]));
                $i++;
            }
            unset($s);
        }
    }

    public static function getAllowedGroups($name = null)
    {
        $groups = array();
        $crm = new waContactRightsModel();
        $gm = new waGroupModel();
        $group_ids = $crm->getAllowedGroups('crm', 'backend');
        $all_groups = $gm->getNames();

        foreach ($group_ids as $id => $right) {
            $g = array(
                'id'   => $id,
                'name' => ifempty($all_groups[$id], $id),
            );
            if ($name) {
                $g['rights'] = $crm->getByField(array('group_id' => $id, 'app_id' => 'crm', 'name' => $name));
            }
            $groups[$id] = $g;
        }
        return $groups;
    }

    public static function getAvailableGroups($right_name = null, $split = false)
    {
        $groups = array();

        $crm = new waContactRightsModel();
        $gm = new waGroupModel();
        $group_ids = $crm->getAllowedGroups('crm', 'backend');
        // $all_rights = $crm->select('*')->where("name='vault.$vault_id' AND group_id > 0")->fetchAll('group_id');
        $all_groups = $gm->getNames();

        $ids = array_map(wa_lambda('$a', 'return -$a;'), array_keys($all_groups));
        $all_rights = $crm->getByIds($ids, 'crm', $right_name, false);

        foreach ($all_groups as $id => $name) {
            $groups[$id] = array(
                'id'     => $id,
                'name'   => $name,
                'rights' => isset($all_rights[$id * -1])
                    ? $all_rights[$id * -1]
                    : (!empty($group_ids[$id]) ? -1 : null),
            );
        }
        uasort($groups, wa_lambda('$a, $b', 'return strcmp($a["name"], $b["name"]);'));

        if (!$split) {
            return $groups;
        }
        $out = array('backend' => array(), 'no_access' => array());
        foreach ($groups as $id => $g) {
            if ($g['rights'] < 0 || $g['rights'] > 0) {
                $out['backend'][$id] = $g;
            } else {
                $out['no_access'][$id] = $g;
            }
        }
        return $out;
    }

    protected static function isTemplate()
    {
        if (waConfig::get('is_template')) {
            throw new waRightsException();
        }
    }

    public static function formatCallNumber($call, $type = 'plugin_client_number')
    {
        $telephony = wa('crm')->getConfig()->getTelephonyPlugins($call['plugin_id']);
        if ($telephony) {
            $number = $telephony->formatClientNumber($call[$type]);
        } else {
            $number = $call[$type];
        }
        return $number ? $number : _w('unknown number');
    }

    public static function formatSeconds($s)
    {
        if (!$s) {
            return '—';
        }
        $format = $s < 3600 ? 'i:s' : 'H:i:s';
        return gmdate($format, $s);
    }

    public static function chooseLegalCsvSeparator($sep = ',')
    {
        $separators = waUtils::getFieldValues(waCSV::$delimiters, '0');
        if (!in_array($sep, $separators)) {
            $options = waCSV::$delimiters;
            $option = reset($options);
            $sep = $option[0];
        }
        return $sep;
    }

    public static function logAction($action, $params = null, $subject_contact_id = null, $contact_id = null)
    {
        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        return $log_model->add($action, $params, $subject_contact_id, $contact_id);
    }

    public static function workupInvoiceItems($invoice)
    {
        $items = array();

        if (isset($invoice['items'])) {
            foreach ($invoice['items'] as $item) {
                $item['price'] = ifempty($item['price'], 0.0);

                $tax_rate = null;
                $tax_included = false;
                if (array_key_exists('tax_included', $item) && array_key_exists('tax_rate', $item)) {
                    $tax_rate = $item['tax_rate'];
                    $tax_included = !!$item['tax_included'];
                } elseif (array_key_exists('tax_type', $item) && array_key_exists('tax_percent', $item)) {
                    if (strtoupper($item['tax_type']) == 'NONE') {
                        $tax_rate = null;
                    } else {
                        $tax_rate = $item['tax_percent'];
                    }
                    $tax_included = strtoupper($item['tax_type']) != 'APPEND';
                }

                $items[] = array(
                    'id'           => ifset($item['id']),
                    'name'         => ifset($item['name']),
                    'tax_included' => $tax_included,
                    'tax_rate'     => $tax_rate,
                    'description'  => '',
                    'price'        => $item['price'],
                    'quantity'     => ifset($item['quantity'], 0),
                    'total'        => $item['price'] * $item['quantity'],
                    'type'         => ifset($item['type'], 'product'),
                    'product_id'   => ifset($item['product_id']),
                );
            }
        }
        return array_values($items);
    }

    public static function getCallRecordLinkHtml($call)
    {
        if (empty($call['plugin_record_id'])) {
            return '';
        }

        if (empty($call['record_attrs'])) {
            $calls = array($call);
            $cm = new crmCallModel();
            $calls = $cm->postProcessCalls($calls);
            $call = reset($calls);
        }

        $attrs = $call['record_attrs'];
        $attrs['id'] = 'c-call-record-'.$call['id'];
        $attrs['title'] = _w('Listen to the recorded conversation');
        $attrs['class'] = ifset($attrs['class'], '').' c-animated-link c-call-record-link';
        foreach ($attrs as $key => $value) {
            $attrs[$key] = $key.'="'.htmlspecialchars($value).'"';
        }
        return '<a '.join(' ', $attrs).'><i class="icon16 play"></i></a>';
    }

    /**
     * @param array $deal
     * @return string
     */
    public static function getMagicSourceEmail($deal)
    {
        if (!$deal) {
            return '';
        }
        if ($deal['status_id'] !== crmDealModel::STATUS_OPEN) {
            return '';
        }

        $sql = "SELECT s.id
                FROM `crm_source` s
                  JOIN `crm_source_params` sp ON s.id = sp.source_id
                    AND sp.name = :name
                WHERE s.disabled <= 0 AND sp.value = :value AND s.type = 'EMAIL'
                LIMIT 1";
        $cm = new crmSourceModel();
        $source_id = (int)$cm->query($sql, array(
            'name' => crmEmailSource::PARAM_EMAIL_SUFFIX_SUPPORTING,
            'value' => crmEmailSource::EMAIL_SUFFIX_SUPPORTING_YES
        ))->fetchField();

        if ($source_id <= 0) {
            return '';
        }
        $source = crmEmailSource::factory((int)$source_id);
        return crmEmailSourceWorkerStrategy::buildMagicEmail($source, $deal);
    }

    public static function titleMinutes($min)
    {
        if ($min < 60 * 3) {
            return sprintf_wp('%d min.', $min);
        } elseif ($min < 60 * 24 * 3) {
            return sprintf_wp('%d h.', $min / 60);
        }
        return sprintf_wp('%d d.', $min / (60 * 24));
    }

    /**
     * Need only for search hash, that got from request_uri
     * Apache might urldecode automaticaly
     * Than webasyst system dispatcher always urldecode
     * So '+' symbol as phone prefix might be eliminated - it is bummer :(
     * So here where dirty hack come
     *
     * @param $hash
     * @return string
     */
    public static function fixPlusSymbolAsPrefixInPhone($hash)
    {
        $operations = array(
            '$=', '^=', '*=', '==', '!=', '>=', '<=', '=', '>', '<',
            '@=', '@$=', '@^=', '@*='
        );

        $quote_symbols = array_map('preg_quote', $operations);
        $ops_pattern = '(' . join('|', $quote_symbols) . '?:)';

        $pattern = '/phone' . $ops_pattern . '\s\d/uis';
        // search for phone<op> <phone_number_query> // there is space between op and phone number query
        if (preg_match($pattern, $hash, $m)) {
            $substr_plus = str_replace(' ', '+', $m[0]);
            $hash = preg_replace($pattern, $substr_plus, $hash);
        }

        return $hash;
    }

    /**
     * Workaround apache AllowEncodedSlashes OFF mode
     * see http://www.leakon.com/archives/865
     *
     * On other (encode) part we convert '%2F' -> '%252F' and '%5C' -> '%255C'
     * %25 is actually the url encoded equivalent of percent (%)
     * In php we now have to has '%2F' for / and %5C for \
     * So convert back to slashes
     *
     * @param $hash
     * @return mixed
     */
    public static function urlDecodeSlashes($hash)
    {
        $hash = str_replace(array('%2F','%5C'), array('/','\\'), $hash);
        return $hash;
    }

    /**
     * Workaround apache AllowEncodedSlashes OFF mode
     * see http://www.leakon.com/archives/865
     *
     * Convert '%2F' -> '%252F' and '%5C' -> '%255C', so apache will not going crazy
     *
     * IMPORTANT: You must call this method on hash that is already urlencoded
     *
     * @param $hash
     * @return mixed
     */
    public static function urlEncodeSlashes($hash)
    {
        $hash = str_replace(array('%2F', '%5C'), array('%252F', '%255C'), $hash);
        return $hash;
    }

    /**
     * Remove emoji from text
     * @param $string
     * @return string
     */
    public static function removeEmoji($string)
    {
        $emoji_pattern = '/[\x{1F3F4}](?:\x{E0067}\x{E0062}\x{E0077}\x{E006C}\x{E0073}\x{E007F})|[\x{1F3F4}](?:\x{E0067}\x{E0062}\x{E0073}\x{E0063}\x{E0074}\x{E007F})|[\x{1F3F4}](?:\x{E0067}\x{E0062}\x{E0065}\x{E006E}\x{E0067}\x{E007F})|[\x{1F3F4}](?:\x{200D}\x{2620}\x{FE0F})|[\x{1F3F3}](?:\x{FE0F}\x{200D}\x{1F308})|[\x{0023}\x{002A}\x{0030}\x{0031}\x{0032}\x{0033}\x{0034}\x{0035}\x{0036}\x{0037}\x{0038}\x{0039}](?:\x{FE0F}\x{20E3})|[\x{1F441}](?:\x{FE0F}\x{200D}\x{1F5E8}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F467}\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F467}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F466}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F466})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F467}\x{200D}\x{1F467})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F466}\x{200D}\x{1F466})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F467}\x{200D}\x{1F466})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F467})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F467}\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F466}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F467}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F466})|[\x{1F469}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F469})|[\x{1F469}\x{1F468}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F468})|[\x{1F469}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F48B}\x{200D}\x{1F469})|[\x{1F469}\x{1F468}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F48B}\x{200D}\x{1F468})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B0})|[\x{1F575}\x{1F3CC}\x{26F9}\x{1F3CB}](?:\x{FE0F}\x{200D}\x{2640}\x{FE0F})|[\x{1F575}\x{1F3CC}\x{26F9}\x{1F3CB}](?:\x{FE0F}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FF}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FE}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FD}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FC}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FB}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F9B8}\x{1F9B9}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F9DE}\x{1F9DF}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F46F}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93C}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FF}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FE}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FD}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FC}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FB}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F9B8}\x{1F9B9}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F9DE}\x{1F9DF}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F46F}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93C}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{200D}\x{2642}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{2695}\x{FE0F})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FF})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FE})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FD})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FC})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FB})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F8}\x{1F1F9}\x{1F1FA}](?:\x{1F1FF})|[\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1F0}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1FA}](?:\x{1F1FE})|[\x{1F1E6}\x{1F1E8}\x{1F1F2}\x{1F1F8}](?:\x{1F1FD})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1F0}\x{1F1F2}\x{1F1F5}\x{1F1F7}\x{1F1F9}\x{1F1FF}](?:\x{1F1FC})|[\x{1F1E7}\x{1F1E8}\x{1F1F1}\x{1F1F2}\x{1F1F8}\x{1F1F9}](?:\x{1F1FB})|[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1ED}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F7}\x{1F1FB}](?:\x{1F1FA})|[\x{1F1E6}\x{1F1E7}\x{1F1EA}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FE}](?:\x{1F1F9})|[\x{1F1E6}\x{1F1E7}\x{1F1EA}\x{1F1EC}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F7}\x{1F1F8}\x{1F1FA}\x{1F1FC}](?:\x{1F1F8})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EA}\x{1F1EB}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1F0}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F8}\x{1F1F9}](?:\x{1F1F7})|[\x{1F1E6}\x{1F1E7}\x{1F1EC}\x{1F1EE}\x{1F1F2}](?:\x{1F1F6})|[\x{1F1E8}\x{1F1EC}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F3}](?:\x{1F1F5})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1EB}\x{1F1EE}\x{1F1EF}\x{1F1F2}\x{1F1F3}\x{1F1F7}\x{1F1F8}\x{1F1F9}](?:\x{1F1F4})|[\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1F0}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FB}](?:\x{1F1F3})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1EB}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F4}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FF}](?:\x{1F1F2})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1EE}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F8}\x{1F1F9}](?:\x{1F1F1})|[\x{1F1E8}\x{1F1E9}\x{1F1EB}\x{1F1ED}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FD}](?:\x{1F1F0})|[\x{1F1E7}\x{1F1E9}\x{1F1EB}\x{1F1F8}\x{1F1F9}](?:\x{1F1EF})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EB}\x{1F1EC}\x{1F1F0}\x{1F1F1}\x{1F1F3}\x{1F1F8}\x{1F1FB}](?:\x{1F1EE})|[\x{1F1E7}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1F0}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}](?:\x{1F1ED})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1EA}\x{1F1EC}\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FB}](?:\x{1F1EC})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F9}\x{1F1FC}](?:\x{1F1EB})|[\x{1F1E6}\x{1F1E7}\x{1F1E9}\x{1F1EA}\x{1F1EC}\x{1F1EE}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F7}\x{1F1F8}\x{1F1FB}\x{1F1FE}](?:\x{1F1EA})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1EE}\x{1F1F2}\x{1F1F8}\x{1F1F9}](?:\x{1F1E9})|[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F8}\x{1F1F9}\x{1F1FB}](?:\x{1F1E8})|[\x{1F1E7}\x{1F1EC}\x{1F1F1}\x{1F1F8}](?:\x{1F1E7})|[\x{1F1E7}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F6}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FB}\x{1F1FF}](?:\x{1F1E6})|[\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}-\x{21AA}\x{231A}-\x{231B}\x{2328}\x{23CF}\x{23E9}-\x{23F3}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}-\x{25AB}\x{25B6}\x{25C0}\x{25FB}-\x{25FE}\x{2600}-\x{2604}\x{260E}\x{2611}\x{2614}-\x{2615}\x{2618}\x{261D}\x{2620}\x{2622}-\x{2623}\x{2626}\x{262A}\x{262E}-\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{2660}\x{2663}\x{2665}-\x{2666}\x{2668}\x{267B}\x{267E}-\x{267F}\x{2692}-\x{2697}\x{2699}\x{269B}-\x{269C}\x{26A0}-\x{26A1}\x{26AA}-\x{26AB}\x{26B0}-\x{26B1}\x{26BD}-\x{26BE}\x{26C4}-\x{26C5}\x{26C8}\x{26CE}-\x{26CF}\x{26D1}\x{26D3}-\x{26D4}\x{26E9}-\x{26EA}\x{26F0}-\x{26F5}\x{26F7}-\x{26FA}\x{26FD}\x{2702}\x{2705}\x{2708}-\x{270D}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2728}\x{2733}-\x{2734}\x{2744}\x{2747}\x{274C}\x{274E}\x{2753}-\x{2755}\x{2757}\x{2763}-\x{2764}\x{2795}-\x{2797}\x{27A1}\x{27B0}\x{27BF}\x{2934}-\x{2935}\x{2B05}-\x{2B07}\x{2B1B}-\x{2B1C}\x{2B50}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F0CF}\x{1F170}-\x{1F171}\x{1F17E}-\x{1F17F}\x{1F18E}\x{1F191}-\x{1F19A}\x{1F201}-\x{1F202}\x{1F21A}\x{1F22F}\x{1F232}-\x{1F23A}\x{1F250}-\x{1F251}\x{1F300}-\x{1F321}\x{1F324}-\x{1F393}\x{1F396}-\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}-\x{1F3F0}\x{1F3F3}-\x{1F3F5}\x{1F3F7}-\x{1F3FA}\x{1F400}-\x{1F4FD}\x{1F4FF}-\x{1F53D}\x{1F549}-\x{1F54E}\x{1F550}-\x{1F567}\x{1F56F}-\x{1F570}\x{1F573}-\x{1F57A}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F590}\x{1F595}-\x{1F596}\x{1F5A4}-\x{1F5A5}\x{1F5A8}\x{1F5B1}-\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}-\x{1F64F}\x{1F680}-\x{1F6C5}\x{1F6CB}-\x{1F6D2}\x{1F6E0}-\x{1F6E5}\x{1F6E9}\x{1F6EB}-\x{1F6EC}\x{1F6F0}\x{1F6F3}-\x{1F6F9}\x{1F910}-\x{1F93A}\x{1F93C}-\x{1F93E}\x{1F940}-\x{1F945}\x{1F947}-\x{1F970}\x{1F973}-\x{1F976}\x{1F97A}\x{1F97C}-\x{1F9A2}\x{1F9B0}-\x{1F9B9}\x{1F9C0}-\x{1F9C2}\x{1F9D0}-\x{1F9FF}]/u';
        return preg_replace($emoji_pattern, ':emoji:', $string);
    }

    /**
     * Get vars of contact for cheat sheets
     * @param string $var_name
     * @return array
     */
    public static function getVarsForContact($var_name = 'customer')
    {
        $vars = array(
            '$'.$var_name.'.birth_day'                       => sprintf(_w('Field %s of customer'), 'birth_day'),
            '$'.$var_name.'.birth_month'                     => sprintf(_w('Field %s of customer'), 'birth_month'),
            '$'.$var_name.'.birth_year'                      => sprintf(_w('Field %s of customer'), 'name'),
            '$'.$var_name.'.name|escape'                     => sprintf(_w('Field %s of customer'), 'name'),
            '$'.$var_name.'.company|escape'                  => sprintf(_w('Field %s of customer'), 'company'),
            '$'.$var_name.'.jobtitle|escape'                 => sprintf(_w('Field %s of customer'), 'jobtitle'),
            '$'.$var_name."->get('phone', 'default|top')"    => sprintf(_w('Field %s of customer'), 'phone'),
            '$'.$var_name."->get('email', 'default')|escape" => sprintf(_w('Field %s of customer'), 'email'),
        );

        $arr_field = array('street', 'city', 'zip', 'region', 'country');

        foreach ($arr_field as $field) {
            $vars['$'.$var_name."->get('address:{$field}', 'default')|escape"] = sprintf(_w('Field %s of customer'), $field);
        }

        return $vars;
    }
}
