<?php

class crmViewAction extends waViewAction
{
    use crmBaseHelpersTrait;
    
    /**
     * @var crmContact
     */
    protected $user_contact;

    public function preExecute()
    {
        if (waRequest::get('ui') === '2.0') {
            waRequest::setParam('force_ui_version', '2.0');
        } elseif (waRequest::get('ui') === '1.3') {
            waRequest::setParam('force_ui_version', '1.3');
        }
        parent::preExecute();
    }

    /**
     * @return crmConfig
     */
    public function getConfig()
    {
        return parent::getConfig();
    }

    /**
     * @return crmContact
     */
    public function getUserContact()
    {
        return $this->user_contact !== null ? $this->user_contact : ($this->user_contact = new crmContact($this->getUserId()));
    }

    /**
     * @param null|string $msg
     * @throws crmNotFoundException
     */
    public function notFound($msg = null)
    {
        throw new crmNotFoundException($msg ? $msg : _w('Page not found'));
    }

    /**
     * @param null|string $msg
     * @throws crmAccessDeniedException
     */
    public function accessDenied($msg = null)
    {
        throw new crmAccessDeniedException($msg ? $msg : _ws('Access denied'));
    }

    public function accessDeniedForNotAdmin($msg = null)
    {
        if (!$this->getCrmRights()->isAdmin()) {
            $this->accessDenied($msg);
        }
    }

    public function accessDeniedForNotEditRights($msg = null)
    {
        if (!wa()->getUser()->getRights('crm', 'edit')) {
            $this->accessDenied($msg);
        }
    }

    /**
     * @param string|null $name
     * @param mixed $default
     * @param string $type
     * @return mixed
     */
    public function getParameter($name = null, $default = null, $type = null)
    {
        $params = (array) $this->params;
        if (array_key_exists($name, $params)) {
            $ext_name = __CLASS__ . "::\$params[{$name}]";
            waRequest::setParam($ext_name, $params[$name]);
            return waRequest::param($ext_name, $default, $type);
        }
        $test_default = uniqid(__METHOD__);
        $value = waRequest::param($name, $test_default);
        if ($value !== $test_default) {
            return waRequest::param($name, $default, $type);
        }
        return waRequest::request($name, $default, $type);
    }

}
