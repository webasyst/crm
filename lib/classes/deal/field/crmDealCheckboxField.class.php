<?php

class crmDealCheckboxField extends crmDealField
{
    /**
     * @param $value
     * @return bool
     */
    public function typecast($value)
    {
        return !empty($value);
    }

    public function format($value, $format = null)
    {
        return $value ? _ws('Yes') : _ws('No');
    }

    public function getHTML($params = array(), $attrs = '')
    {
        $value = isset($params['value']) ? $params['value'] : '';
        $disabled = '';
        if (wa()->getEnv() === 'frontend' && isset($params['my_profile']) && $params['my_profile'] == '1') {
            $disabled = 'disabled="disabled"';
        }
        return '<input type="hidden" '.$disabled.' '.$attrs.' name="'.$this->getHTMLName($params).'" value=""><input type="checkbox"'.($value ? ' checked="checked"' : '').' name="'.$this->getHTMLName($params).'" value="'.ifempty($value, '1').'" '.$attrs.'>';
    }
}
