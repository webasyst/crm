<?php

class crmPop3EmailSource extends crmEmailSource
{
    protected $provider = 'pop3';

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
            if ($this->exists()) {
                $this->saveParam('connection_hash', $hash);
            } else {
                $this->setParam('connection_hash', $hash);
            }
        } else {
            $this->deleteParam('connection_hash');
        }
        return $errors;
    }

    protected function choseSecureFlag($params)
    {
        $ssl = (string)ifset($params['ssl']);
        $tls = (string)ifset($params['tls']);
        unset($params['ssl'], $params['tls']);
        if ($ssl) {
            return 'ssl';
        } elseif ($tls) {
            return 'tls';
        }
        return null;
    }

    /**
     * @return array
     */
    public function getConnectionParams()
    {
        $params = $this->getParams();
        $connection_params = array();
        foreach (array('email', 'login', 'server', 'port', 'password') as $key) {
            $connection_params[$key] = (string)ifset($params[$key]);
        }

        if (isset($params['stream_context_options']) && is_array($params['stream_context_options'])) {
            $connection_params['stream_context_options'] = $params['stream_context_options'];
        }

        $flag = $this->choseSecureFlag($params);
        if ($flag) {
            $connection_params[$flag] = 1;
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

        $flag = $this->choseSecureFlag($params);
        if ($flag) {
            $connection_params[$flag] = 1;
        }

        $this->unsetParam('ssl');
        $this->unsetParam('tls');
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
        $connection_params['tls'] = null;

        $flag = $this->choseSecureFlag($params);
        if ($flag) {
            $connection_params[$flag] = 1;
        }

        $this->saveParams($connection_params, false);
    }

    protected function tryToConnect(array $params)
    {
        $flag = $this->choseSecureFlag($params);

        if (isset($params['ssl'])) {
            unset($params['ssl']);
        }
        if (isset($params['tls'])) {
            unset($params['tls']);
        }

        if ($flag) {
            $params[$flag] = 1;
        }

        $errors = array();
        try {
            // Check if SSL is supported
            if (!defined('OPENSSL_VERSION_NUMBER') && !empty($params['ssl'])) {
                $errors['ssl'] = _w('Encryption requires OpenSSL PHP module to be installed.');
            } else {
                $options = $params;
                $options['timeout'] = 20;
                $mail_reader = new waMailPOP3($options);
                $mail_reader->count();
                $mail_reader->close();
            }
        } catch (Exception $e) {
            $err = $e->getMessage();
            if (!$err || $err == ' ()') {
                $err = _w('Unknown error.');
            } else if (false !== strpos($err, 'IMAP')) {
                $err = _w('IMAP is not supported. Please use POP3.');
            }
            $errors[''] = _w('An error occurred while attempting to connect with specified settings.').' '.$err;
        }
        return $errors;
    }
}
