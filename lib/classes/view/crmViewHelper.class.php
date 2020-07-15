<?php

class crmViewHelper
{
    protected static $url = '';

    /**
     * @param $count
     * @param $page
     * @param $url_params
     * @param int $limit
     * @return string
     */
    public function pager($count, $page, $url_params = '', $limit = crmConfig::ROWS_PER_PAGE)
    {
        $width = 5;
        $html = '';
        $page = max($page, 1);
        self::$url = '?page=';
        $url_params = trim(trim($url_params), '&?');
        $total = 0;
        if (isset($count['folders']) && isset($count['files']) &&
            is_numeric($count['folders']) &&
            is_numeric($count['files'])
        ) {
            $total = intval($count['folders']) + intval($count['files']);
        } elseif (is_numeric($count)) {
            $total = $count;
        }
        if ($total) {
            $pages = ceil($total / $limit);
            if ($pages > 1) {
                $page = intval($page);
                $html = '<ul class="pager">';
                if (is_numeric($count)) {
                    $html .= '<li>'._w('Total:').' <em>'.$count.'</em></li>';
                }
                if (!empty($count['folders'])) {
                    $html .= '<li>'._w('Folders:').' <em>'.$count['folders'].'</em></li>';
                }
                if (!empty($count['files'])) {
                    $html .= '<li>'._w('Files:').' <em>'.$count['files'].'</em></li>';
                }

                $html .= ' <span>'._w('Page:').'</span></li>';

                if ($page > 1) {
                    $title = _w('prev');
                    $url = self::$url . ($page - 1) . (strlen($url_params) > 0 ? '&' . $url_params : '');
                    $html .= "<li><a href='{$url}' title='{$title}'><i class='icon10 larr'></i>{$title}</a></li>";
//                } else {
//                    $html .= "<li><i>&lt;</i></li>";
                }

                $html .= self::item(1, $page, $url_params);
                for ($i = 2; $i < $pages; $i++) {
                    if (abs($page - $i) < $width ||
                        ($page - $i == $width && $i == 2) ||
                        ($i - $page == $width && $i == $pages - 1)
                    ) {
                        $html .= self::item($i, $page, $url_params);
                    } elseif (strpos(strrev($html), '...') != 5) { // 5 = strlen('</li>')
                        $html .= '<li>...</li>';
                    }
                }

                $html .= self::item($pages, $page, $url_params);

                if ($page < $pages) {
                    $title = _w('next');
                    $url = self::$url . ($page + 1) . (strlen($url_params) > 0 ? '&' . $url_params : '');
                    $html .= "<li><a href='{$url}' title='{$title}'>{$title} <i class='icon10 rarr'></i></a></li>";
//                } else {
//                    $html .= "<li><i>&gt;</i></li>";
                }
            }
        }
        return $html;
    }

    /**
     * @param $i
     * @param $page
     * @param $url_params
     * @return string
     */
    protected static function item($i, $page, $url_params = '')
    {
        if ($page != $i) {
            $url = self::$url . $i . (strlen($url_params) > 0 ? '&' . $url_params : '');
            return "<li><a href='{$url}'>{$i}</a></li>";
        } else {
            return "<li class='selected'>{$i}</li>";
        }
    }

    /**
     * @param $id
     * @param array $options
     *        bool $options['ignore_include_jquery'] not include jquery lib in output html
     *        bool $options['ignore_include_jquery_ui'] not include jquery-ui lib (js & css) in output html
     *        bool $options['ignore_include_jquery_ui_css'] not include jquery-ui css styles in output html
     *        bool $options['ignore_include_jquery_ui_js'] not include jquery-ui js lib in output html
     * @return string
     */
    public function form($id, $options = array())
    {
        $app_id = 'crm';
        if (!wa()->getRouting()->getByApp($app_id, wa()->getRouting()->getDomain())) {
            waLocale::loadByDomain($app_id);
            $msg = _wd($app_id, 'Routing rules are not defined for CRM app');
            return '<p class="errormsg c-routing-error">' . $msg . '</p>';
        }

        $old_app = wa()->getApp();
        wa('crm', true);
        $is_from_template = waConfig::get('is_template');
        waConfig::set('is_template', null);
        try {
            $form = new crmFormRenderer($id, $options);
            $html = $form->render();
        } catch (Exception $e) {
            $html = '';
        }
        waConfig::set('is_template', $is_from_template);
        wa($old_app, true);
        return $html;
    }

    public function getContactFields()
    {
        $fields = array();
        foreach (waContactFields::getAll('person', true) as $field_id => $field) {
            $fields[$field_id] = $field->getInfo();
            $fields[$field_id]['top'] = $field->getParameter('top');
        }
        $default_exts = array(
            'email' => array(
                'work' => _ws('work'),
                'personal' => _ws('personal')
            ),
            'phone' => array(
                'work' => _ws('work'),
                'mobile' => _ws('mobile'),
                'home' => _ws('home')
            )
        );
        foreach (array('email', 'phone') as $field_id) {
            if (empty($fields[$field_id])) {
                $fields[$field_id] = array('id' => $field_id);
            }
            if (empty($fields[$field_id]['ext'])) {
                $fields[$field_id]['ext'] = $default_exts[$field_id];
            }
        }
        return $fields;
    }

    public function contactName($name)
    {
        return $name ? htmlspecialchars($name) : '('._w('no name').')';
    }

    public function namePlaceholder()
    {
        $c = new waContact();
        $c['firstname'] = _w('Firstname');
        $c['middlename'] = _w('Middlename');
        $c['lastname'] = _w('Lastname');
        return $c['name'];
    }
}
