<?php

class crmDealDateField extends crmDealField
{
    public function getInfo()
    {
        $info = parent::getInfo();
        $info['format'] = waDateTime::getFormatJS('date');
        return $info;
    }

    public function validate($value)
    {
        /** @var waValidator $validator */
        $validator = ifset($this->options, 'validators', new waDateValidator());
        if ($validator->isValid($value)) {
            return array();
        }
        return $validator->getErrors();
    }

    public function format($value, $format = null)
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = (string)$value;
        if ($value == '0000-00-00') {
            return null;
        }
        if ($format === null) {
            $format = 'date';
        }
        return waDateTime::format($format, $value, 'server');
    }

    public function typecast($value)
    {
        if (!is_scalar($value)) {
            return null;
        }
        if (is_numeric($value) && $value >= 0) {
            return date('Y-m-d', (int)$value);
        }

        $value = (string)$value;
        $time = strtotime($value);

        if ($time === false || $time <= 0) {
            return null;
        }
        return date('Y-m-d', $time);
    }
}
