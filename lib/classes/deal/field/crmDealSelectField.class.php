<?php

class crmDealSelectField extends crmDealField
{
    public function __construct($id, $name, array $options = array())
    {
        if (isset($options['options'])) {
            foreach ($options['options'] as $key => $value) {
                if (is_scalar($value)) {
                    $options['options'][$key] = (string)$value;
                }
            }
        }
        parent::__construct($id, $name, $options);
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        if (!isset($this->options['options']) || !is_array($this->options['options'])) {
            return array();
        }
        $options = $this->options['options'];
        foreach($options as &$o) {
            $o = _w($o);
        }
        return $options;
    }

    protected function getOptionsArray($loc = false)
    {
        if (!isset($this->options['options']) || !is_array($this->options['options'])) {
            return array();
        }
        $options = $this->options['options'];
        if (!$loc) {
            return $options;
        }

        foreach($options as &$o) {
            $o = _w($o);
        }
        return $options;
    }

    public function getInfo()
    {
        $data = parent::getInfo();
        $data['options'] = $this->getOptions();

        // In JS we cannot rely on the order of object properties during iteration
        // so we pass an order of keys as an array
        $data['oOrder'] = array_keys($data['options']);
        $data['defaultOption'] = _w($this->getParameter('defaultOption'));
        return $data;
    }

    public function validate($value)
    {
        if ($this->typecast($value) !== null) {
            return array();
        }
        return array(_w('Unknown value'));
    }

    /**
     * Return 'Select' type, unless redefined in subclasses
     * @return string
     */
    public function getType()
    {
        return 'Select';
    }

    public function format($value, $format = null)
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = (string)$value;
        $options = $this->getOptions();
        return isset($options[$value]) ? $options[$value] : null;
    }

    public function typecast($value)
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = (string)$value;

        $options = $this->getOptionsArray();
        if (isset($options[$value])) {
            return $value;
        }

        $res = $this->findKeyByValue($options, $value);
        if ($res !== null) {
            return $res;
        }

        $options_loc = $this->getOptionsArray(true);
        $res = $this->findKeyByValue($options_loc, $value);

        return $res;
    }

    public function getHtml($params = array(), $attrs = '')
    {
        $selected_map = array();
        if (isset($params['value'])) {
            $selected_map[$params['value']] = true;
            $selected_map[$this->typecast($params['value'])] = true;
        }
        $html = '<select '.$attrs.' name="'.$this->getHTMLName($params).'"><option value=""></option>';
        foreach ($this->getOptions() as $k => $v) {
            $selected = isset($selected_map[$k]) ? ' selected="selected"' : '';
            $html .= '<option'.$selected.' value="'.htmlspecialchars($k).'">'.htmlspecialchars($v).'</option>';
        }
        $html .= '</select>';
        return $html;
    }

    /**
     * @param $values
     * @param $value
     * @return int|null|string
     */
    protected function findKeyByValue($values, $value)
    {
        $value_lower = mb_strtolower($value);
        foreach ($values as $key => $val) {
            if ($val === $value) {
                return $key;
            }
            $val_lower = mb_strtolower($val);
            if ($val_lower === $value_lower) {
                return $key;
            }
        }
        return null;
    }
}
