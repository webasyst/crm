<?php

/**
 * Class crmImapPluginEmailSource
 */
class crmImapPluginEmailSource extends crmEmailSource
{
    protected $provider = 'imap';

    /**
     * @return array errors if connection is failed
     */
    public function testConnection()
    {
        $connection_params = $this->getConnectionParams();

        ksort($connection_params);
        $hash = md5(serialize($connection_params));

        if ($this->getParam('connection_hash') === $hash) {
            return array();
        }

        $errors = $this->tryToConnect($connection_params);
        if (!$errors) {
            $this->saveParam('connection_hash', $hash);
        } else {
            $this->deleteParam('connection_hash');
        }
        return $errors;
    }

    public function getIcon()
    {
        return wa()->getAppStaticUrl('crm/plugins/imap/img', true) . 'imap.png';
    }

    /**
     * @return array
     */
    public function getConnectionParams()
    {
        $params = $this->getParams();
        $connection_params = array();
        foreach (array('email', 'login', 'server', 'port', 'password', 'ssl') as $key) {
            $connection_params[$key] = (string)ifset($params[$key]);
        }
        return $connection_params;
    }

    public function setConnectionParams($params)
    {
        $connection_params = array();
        foreach (array('email', 'login', 'server', 'port', 'password') as $key) {
            if (isset($params[$key])) {
                $connection_params[$key] = $params[$key];
            } else {
                $connection_params[$key] = $this->getParam($key);
            }
        }

        if (!empty($params['ssl'])) {
            $connection_params['ssl'] = 1;
        }

        $this->unsetParam('ssl');
        $this->setParams($connection_params, false);
    }

    public function saveConnectionParams($params)
    {
        $connection_params = array();
        foreach (array('email', 'login', 'server', 'port', 'password') as $key) {
            if (isset($params[$key])) {
                $connection_params[$key] = $params[$key];
            } else {
                $connection_params[$key] = $this->getParam($key);
            }
        }

        $connection_params['ssl'] = null;
        if (!empty($params['ssl'])) {
            $connection_params['ssl'] = 1;
        }

        $this->saveParams($connection_params, false);
    }

    protected function tryToConnect(array $params)
    {
        $errors = array();
        try {
            // Check if SSL is supported
            if (!defined('OPENSSL_VERSION_NUMBER') && !empty($params['ssl'])) {
                $errors['ssl'] = _w('Encryption requires OpenSSL PHP module to be installed.');
            } else {
                $mail_reader = new crmImapPluginMailReader($params);
                $mail_reader->count();
                $mail_reader->close();
            }
        } catch (Exception $e) {
            $err = $e->getMessage();
            if (!$err || $err == ' ()') {
                $err = _w('Unknown error.');
            }
            $errors[''] = _w('An error occured while attempting to connect with specified settings.').' '.$err;
        }
        return $errors;
    }
}
