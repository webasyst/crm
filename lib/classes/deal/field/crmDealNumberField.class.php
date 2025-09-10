<?php

class crmDealNumberField extends crmDealField
{
    public function format($value, $format = null)
    {
        return $this->typecast($value);
    }

    public function validate($value)
    {
        if (empty($value)) {
            return [];
        }
        if (!is_scalar($value)) {
            return [_ws('Incorrect numerical value')];
        }
        $validator = new waNumberValidator();
        if ($validator->isValid($value)) {
            return [];
        }
        return $validator->getErrors();
    }

    public function typecast($value)
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }
        if (wa_is_int($value)) {
            $value = intval($value);
        } elseif (is_numeric($value)) {
            $value = floatval($value);
        } elseif (is_string($value) && strlen(trim($value)) <= 0) {
            $value = '';
        } else {
            $value = 0;
        }
        return $value;
    }

    public function getHtml($params = array(), $attrs = '')
    {
        if (array_key_exists('value', $params)) {
            $params['value'] = $this->typecast($params['value']);
            if ($params['value'] === null) {
                unset($params['value']);
            }
        }
        return parent::getHtml($params, $attrs);
    }
}
