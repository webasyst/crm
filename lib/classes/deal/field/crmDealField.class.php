<?php

abstract class crmDealField
{
    /**
     * @var crmModel[]
     */
    static protected $models;

    protected $id;

    /**
     * Available options
     *
     * array(
     *     'export' => bool,      // !!! never used?..
     *     'sort' => bool,        // ?..
     *     'pa_hidden' => bool,   // do not show in personal account
     *     'pa_readonly' => bool, // show as read-only in personal account
     *     'unique' => bool,      // only allows unique values
     *     'search' => bool,      // ?..
     *     // any options for specific field type
     *     ...
     * )
     */
    protected $options;

    /** array(locale => name) */
    protected $name = array();

    /** used by __set_state() */
    protected $_type;

    /**
     * Constructor
     *
     * Because of a specific way this class is saved and loaded via var_dump,
     * constructor parameters order and number cannot be changed in subclasses.
     * Subclasses also must always provide a call to parent's constructor.
     *
     * @param string $id
     * @param mixed $name either a string or an array(locale => name)
     * @param array $options
     */
    public function __construct($id, $name, $options = array())
    {
        $this->id = $id;
        $this->setParameter('localized_names', $name);
        $this->options = $options;
        $this->_type = get_class($this);
        $this->init();
    }


    protected function init()
    {

    }

    /**
     * Returns id of the field
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    public function getInfo()
    {
        return array(
            'id'       => $this->id,
            'name'     => $this->getName(),
            'type'     => $this->getType(),
            'class'    => get_class($this)
        );
    }

    /**
     * @return array
     */
    public function validate($value)
    {
        return array();
    }

    /**
     * @param string $value
     * @param string|null $format
     * @return mixed
     */
    public function format($value, $format = null)
    {
        return $value;
    }

    /**
     * @param $value
     * @return mixed
     */
    public function typecast($value)
    {
        return $value;
    }

    public function isMulti()
    {
        return isset($this->options['multi']) && $this->options['multi'];
    }

    public function isUnique()
    {
        return isset($this->options['unique']) && $this->options['unique'];
    }

    public function isRequired()
    {
        return isset($this->options['required']) && $this->options['required'];
    }

    /**
     * Returns name of the field
     *
     * @param string $locale - locale
     * @param bool $escape
     * @return string
     */
    public function getName($locale = null, $escape = false)
    {
        if (!$locale) {
            $locale = waSystem::getInstance()->getLocale();
        }

        if (isset($this->name[$locale])) {
            $name = $this->name[$locale];
        } elseif (isset($this->name['en_US'])) {
            if ($locale == waSystem::getInstance()->getLocale() && wa()->getEnv() == 'backend') {
                $name = _ws($this->name['en_US']);
            } else {
                $name = waLocale::translate('webasyst', $locale, $this->name['en_US']);
            }
        } else {
            $name = reset($this->name); // reset() returns the first value
        }
        return $escape ? htmlspecialchars($name, ENT_QUOTES, 'utf-8') : $name;
    }

    /**
     * @return string
     */
    public function getType()
    {
        if (isset($this->options['type'])) {
            return $this->options['type'];
        }
        return str_replace(array('crmDeal', 'Field'), array('', ''), get_class($this));
    }

    /**
     * Get the current value of option $p.
     * Used by a Field Constructor editor to access field parameters.
     *
     * crmDealField has one parameter: localized_names = array(locale => name)
     *
     * @param $p string parameter to read
     * @return array|null
     */
    public function getParameter($p)
    {
        if ($p == 'localized_names') {
            return $this->name;
        }

        if (!isset($this->options[$p])) {
            return null;
        }
        return $this->options[$p];
    }

    /**
     * Set the value of option $p.
     * Used by a Field Constructor editor to change field parameters.
     *
     * localized_names = array(locale => name)
     * required = boolean
     * unique = boolean
     *
     * @param $p string parameter to set
     * @param $value mixed value to set
     */
    public function setParameter($p, $value)
    {
        if ($p == 'localized_names') {
            if (is_array($value)) {
                if (!$value) {
                    $value['en_US'] = '';
                }
                $this->name = $value;
            } else {
                $this->name = array('en_US' => $value);
            }
            return;
        }

        $this->options[$p] = $value;
    }

    public function getParameters()
    {
        $options = $this->options;
        $options['localized_names'] = $this->name;
        return $options;
    }

    /**
     * Set array of parameters
     * @param array $param parameter => value
     * @throws waException
     */
    public function setParameters($param)
    {
        if (!is_array($param)) {
            throw new waException('$param must be an array: '.print_r($param, true));
        }
        foreach ($param as $p => $val) {
            $this->setParameter($p, $val);
        }
    }

    /**
     * Get funnels parameters
     * @return array<id, array<string, mixed>> - key-value parameters indexed by funnel ID
     */
    public function getFunnelsParameters()
    {
        $funnels_parameters = $this->getParameter('funnels_parameters');
        if (!is_array($funnels_parameters)) {
            $funnels_parameters = [];
        }
        return $funnels_parameters;
    }

    /**
     * Get one specified funnel parameters
     * @param int $funnel_id - funnel ID
     * @return array<string, mixed> - key-value parameters
     */
    public function getFunnelParameters($funnel_id)
    {
        $parameters = $this->getFunnelsParameters();
        return isset($parameters[$funnel_id]) ? $parameters[$funnel_id] : [];
    }

    /**
     * Get one specific parameter for one specific funnel
     * @param int $funnel_id - funnel ID
     * @param string $name - name of parameter to get
     * @return mixed|null
     */
    public function getFunnelParameter($funnel_id, $name)
    {
        $parameters = $this->getFunnelParameters($funnel_id);
        return isset($parameters[$name]) ? $parameters[$name] : null;
    }

    /**
     * Set funnels parameters
     * Method validate input before save, if input is not good returns false
     * @param array<id, array<string, mixed>> - key-value parameters indexed by funnel ID
     * @return bool
     */
    public function setFunnelsParameters($funnels_parameters)
    {
        if (!$this->isFunnelsParametersValid($funnels_parameters)) {
            return false;
        }
        $this->setParameter('funnels_parameters', $funnels_parameters);
        return true;
    }

    /**
     * Set parameters for one funnel
     * Method validate input before save, if input is not good returns false
     * @param int $funnel_id - funnel ID
     * @param array<string, mixed>
     * @return bool
     */
    public function setFunnelParameters($funnel_id, $parameters)
    {
        if (!$this->isFunnelsParametersValid([$funnel_id => $parameters])) {
            return false;
        }
        $funnel_parameters = $this->getFunnelsParameters();
        $funnel_parameters[$funnel_id] = $parameters;
        return $this->setFunnelsParameters($funnel_parameters);
    }

    /**
     * Set one specific parameter to one specific funnel
     * Method validate input before save, if input is not good returns false
     * @param int $funnel_id - funnel ID
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    public function setFunnelParameter($funnel_id, $name, $value)
    {
        if (!$this->isFunnelsParametersValid([$funnel_id => [$name => $value]])) {
            return false;
        }
        $funnel_parameters = $this->getFunnelsParameters();
        $funnel_parameters[$funnel_id][$name] = $value;
        return $this->setFunnelsParameters($funnel_parameters);
    }

    /**
     * Validate input array
     * @param array<id, array<string, mixed>> $funnels_parameters - key-value parameters indexed by funnel ID
     * @return bool
     */
    protected function isFunnelsParametersValid($funnels_parameters)
    {
        if (!is_array($funnels_parameters)) {
            return false;
        }
        foreach ($funnels_parameters as $funnel_id => $parameters) {
            if (!wa_is_int($funnel_id) || $funnel_id <= 0) {
                return false;
            }
            if (!is_array($parameters)) {
                return false;
            }
        }
        return true;
    }

    protected function getHTMLName($params)
    {
        $prefix = $suffix = '';
        if (isset($params['namespace'])) {
            $prefix .= $params['namespace'].'[';
            $suffix .= ']';
        }
        if (isset($params['parent'])) {
            if ($prefix) {
                $prefix .= $params['parent'].'][';
            } else {
                $prefix .= $params['parent'].'[';
                $suffix .= ']';
            }
        }

        if (isset($params['multi_index'])) {
            if (isset($params['parent'])) {
                // For composite multi-fields multi_index goes before field id:
                // namespace[parent_name][i][field_id]
                $prefix .= $params['multi_index'].'][';
            } else {
                // For non-composite multi-fields multi_index goes after field id:
                // namespace[field_id][i]
                $suffix = ']['.$params['multi_index'].$suffix;
            }
        }
        $name = isset($params['id']) ? $params['id'] : $this->getId();

        return $prefix.$name.$suffix;
    }

    public function getHTML($params = array(), $attrs = '')
    {
        $value = '';
        if (array_key_exists('value', $params)) {
            $value = (string)$this->format($params['value']);
        }

        $name_input = $name = $this->getHTMLName($params);

        $disabled = '';
        if (wa()->getEnv() === 'frontend' && isset($params['my_profile']) && $params['my_profile'] == '1') {
            $disabled = 'disabled="disabled"';
        }

        $name = $this->getName(null, true);
        if (!empty($params['placeholder'])) {
            $attrs .= ' placeholder="' . htmlspecialchars($name) . '"';
        }

        $result = '<input '.$attrs.' title="'.$name.'" '.$disabled.' type="text" name="'.htmlspecialchars($name_input).'" value="'.htmlspecialchars($value).'">';

        return $result;
    }

    public function prepareVarExport()
    {
    }

    /**
     * @param $state
     * @return crmDealField
     */
    public static function __set_state($state)
    {
        return new $state['_type']($state['id'], $state['name'], $state['options']);
    }

    public function prepareSave($value)
    {
        return $value;
    }
}
