<?php

class crmDealTextField extends crmDealStringField
{
    public function getHtml($params = array(), $attrs = '')
    {
        $value = '';
        if (array_key_exists('value', $params)) {
            $value = $this->typecast($params['value']);
            if ($value === null) {
                $value = '';
            }
        }

        $name = $this->getName(null, true);
        if (!empty($params['placeholder'])) {
            $attrs .= ' placeholder="' . htmlspecialchars($name) . '"';
        }
        return '<textarea '.$attrs.' name="'.$this->getHTMLName($params).'" title="'.$name.'">'.htmlspecialchars($value).'</textarea>';
    }
}
