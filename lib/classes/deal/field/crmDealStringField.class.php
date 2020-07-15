<?php

class crmDealStringField extends crmDealField
{
    public function format($value, $format = null)
    {
        return $this->typecast($value);
    }

    public function validate($value)
    {
        if (!is_scalar($value)) {
            return array(_w('Not a string'));
        }
        $value = (string)$value;
        $validator = new waStringValidator();
        if ($validator->isValid($value)) {
            return array();
        }
        return $validator->getErrors();
    }

    public function typecast($value)
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }
        return (string)$value;
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
