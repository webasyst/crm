<?php

class crmDealRadioField extends crmDealSelectField
{
    public function getHTML($params = array(), $attrs = '')
    {
        $value = isset($params['value']) ? $params['value'] : '';

        $disabled = '';
        if (wa()->getEnv() === 'frontend' && isset($params['my_profile']) && $params['my_profile'] == '1') {
            $disabled = 'disabled="disabled"';
        }

        $html = '<label><input type="hidden" '.$disabled.' '.$attrs.' name="' . $this->getHTMLName($params) . '" value=""></label>';
        foreach ($this->getOptions() as $k => $v) {
            $html .= '<label><input type="radio"'.($k == $value ? ' checked="checked"' : '').' '.$disabled.' '.$attrs.' name="'.$this->getHTMLName($params).'" value="'.$k.'"> '.htmlspecialchars($v).'</label>';
        }

        $dom_id = uniqid('c-deal-radio-field');
        $link_click_js = "$('#{$dom_id} :checked').removeAttr('checked');";
        $link_name = _w('clear');

        $clear_link = "<a class='js-clear-link clear-link hint' href='javascript:void(0)' onclick=\"{$link_click_js};\">{$link_name}</a>";

        return "<p id='{$dom_id}'>{$html}{$clear_link}</p>";
    }
}
