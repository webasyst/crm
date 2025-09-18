<?php

class crmWaFrontendHeadHandler extends waEventHandler
{
    public function execute(&$params)
    {
        $domain = $params['domain'];
        $form_params_model = new crmFormParamsModel();
        $widget_forms = $form_params_model->getByField([
            'name' => 'widget_container',
            'value' => ['dialog', 'drawer'],
        ], 'form_id');
        $widget_form_ids = array_keys($widget_forms);
        if (empty($widget_form_ids)) {
            return;
        }

        $widget_forms = $form_params_model->getByField([
            'form_id' => $widget_form_ids,
            'name' => ['widget_container', 'widget_display_fab', 'widget_display_fab_color', 'widget_display_fab_text', 'widget_display_fab_icon', 'widget_display_custom', 'widget_display_custom_selector', 'widget_display_on_timeout', 'widget_display_timeout', 'widget_display_on_scroll', 'widget_display_scroll', 'widget_domains', 'widget_path', 'widget_header', 'widget_show_after_timeout', 'widget_theme', 'max_width'],
        ], true);

        $widget_forms = array_map(function ($item) {
            if (in_array($item['name'], ['widget_domains', 'widget_path'])) {
                $item['value'] = json_decode($item['value'], true);
            }
            return $item;
        }, $widget_forms);

        $widget_forms = array_reduce($widget_forms, function ($result, $item) {
            if (isset($result[$item['form_id']])) {
                $result[$item['form_id']][$item['name']] = $item['value'];
            } else {
                $result[$item['form_id']] = [$item['name'] => $item['value']];
            }
            return $result;
        }, []);
        $uri = waRequest::server('REQUEST_URI');
        
        $widget_forms = array_filter($widget_forms, function ($item) use ($domain, $uri) {
            if (!in_array($domain, $item['widget_domains'])) {
                return false;
            }
            $paths = ifset($item, 'widget_path', $domain, '');
            if (empty($paths)) {
                return true;
            }
            if (is_scalar($paths)) {
                $paths = [$paths];
            }
            $paths = array_filter($paths, function ($path) use ($uri) {
                return empty($path) || $uri === $path || (strpos(strrev($path), '*') === 0 && strpos($uri, substr($path, 0, -1)) === 0);
            });
            return !empty($paths);
        });
        
        if (empty($widget_forms)) {
            return;
        }

        $app_static_url = wa()->getAppStaticUrl('crm');
        $result = '';
        $head_addon = '';
        foreach ($widget_forms as $id => $widget_form) {
            $iframe_url = wa()->getRouteUrl('crm/frontend/formIframe', ['id' => $id], true, $domain) ?: wa()->getRouteUrl('crm/frontend/formIframe', ['id' => $id], true);
            $header = json_encode(ifset($widget_form['widget_header'], ''));
            $max_width = ifempty($widget_form['max_width'], 400) + 80;
            $display_container = json_encode(ifempty($widget_form['widget_container'], 'dialog'));
            $display_fab = ifset($widget_form['widget_display_fab'], false);
            $display_fab_icon = ifempty($widget_form['widget_display_fab_icon'], '');
            $display_fab_text = ifempty($widget_form['widget_display_fab_text'], empty($display_fab_icon) ? _ws('Subscribe') : '');
            $display_fab_color = ifempty($widget_form['widget_display_fab_color'], '#cc528f');
            $display_custom = ifset($widget_form['widget_display_custom'], false);
            $display_custom_selector = ifempty($widget_form['widget_display_custom_selector'], null);
            $display_on_timeout = ifset($widget_form['widget_display_on_timeout'], false);
            $display_on_scroll = ifset($widget_form['widget_display_on_scroll'], false);
            $display_conditions = $display_fab ? ', display_conditions: "fab", display_fab_text: ' . json_encode($display_fab_text) . ', display_fab_icon: ' . json_encode($display_fab_icon) . ', display_fab_color: ' . json_encode($display_fab_color) : '';
            $custom_element = $display_custom && !empty($display_custom_selector) ? ', custom_element: ' . json_encode($display_custom_selector) : '';
            $display_timeout = $display_on_timeout ? ', display_timeout: ' . ifset($widget_form['widget_display_timeout'], 1) * 1000 : '';
            $display_scroll = $display_on_scroll ? ', display_scroll: ' . ifset($widget_form['widget_display_scroll'], 10) : '';
            $show_after_timeout = ifset($widget_form['widget_show_after_timeout'], 24) * 60 * 60 * 1000;
            if (!$show_after_timeout) {
                $show_after_timeout = 1;
            }
            $theme = empty($widget_form['widget_theme']) ? '' : ', theme: ' . json_encode($widget_form['widget_theme']);
            $powered_by = crmHelper::isPremium() ? ', no_brending: true' : '';

            if (!empty($iframe_url) && ($display_fab || !empty($custom_element) || $display_on_timeout || $display_on_scroll)) {
                $iframe_url = json_encode($iframe_url);
                $locale = wa()->getLocale() == 'ru_RU' ? 'ru' : 'en';
                $head_addon .= <<<EOT
createCRMWidget({
    locale: '$locale',
    iframe_url: $iframe_url,
    header: $header,
    max_width: $max_width,
    show_after_timeout: $show_after_timeout,
    display_container: $display_container $display_conditions $custom_element $display_timeout $display_scroll $theme $powered_by
});

EOT;
            }
        }

        if ($head_addon) {
            $result = <<<EOT
<script src="{$app_static_url}js/form/widget.min.js"></script>
<script>
$head_addon
</script>
EOT;
        }
        return $result;
    }
}
