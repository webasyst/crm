<?php

/**
 * Email source: IMAP via {@link crmImap4PluginMailImap} (PHP imap extension if available; else sockets).
 * Optional “leave on server” mode with UID tracking to avoid duplicate imports.
 */
class crmImap4PluginEmailSource extends crmEmailSource
{
    protected $provider = 'imap4';

    /**
     * @return array<string, array{server: string, port: int, ssl: int}>
     */
    public static function getImapProviderPresets()
    {
        return crmEmailSource::getMailProviderPresets('imap');
    }

    /**
     * @return array<string, string>
     */
    public static function getImapProvidersForUi()
    {
        return crmEmailSource::getMailProvidersForUi();
    }

    /**
     * @param array $params reference — merged preset server/port/ssl when imap_provider is not custom
     */
    public static function applyImapProviderPresetToParams(array &$params)
    {
        crmEmailSource::applyMailProviderPresetToParams($params, 'imap', 'imap_provider');
    }

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

    public function getIcon()
    {
        return wa()->getAppStaticUrl('crm/plugins/imap4/img', true).'imap.png';
    }

    public function getFontAwesomeIcon()
    {
        return [
            'icon_fa' => 'envelope',
            'icon_color' => 'darkorange',
        ];
    }

    /**
     * Connection / behaviour flags for {@link crmImap4PluginMailReader} (excludes last_imap_uid).
     *
     * @return array
     */
    public function getConnectionParams()
    {
        $params = $this->getParams();
        self::applyImapProviderPresetToParams($params);

        $connection_params = array();
        foreach (array('email', 'login', 'server', 'port', 'password', 'ssl') as $key) {
            $connection_params[$key] = (string) ifset($params[$key]);
        }

        $connection_params['imap_provider'] = isset($params['imap_provider']) ? (string) $params['imap_provider'] : 'custom';

        $lm = array_key_exists('leave_messages_on_server', $params) ? (int) $params['leave_messages_on_server'] : 1;
        $connection_params['leave_messages_on_server'] = ($lm === 1) ? 1 : 0;

        $connection_params['skip_existing_on_create'] = array_key_exists('skip_existing_on_create', $params)
            ? (((int) $params['skip_existing_on_create'] === 1) ? 1 : 0)
            : 0;

        if (isset($params['stream_context_options']) && is_array($params['stream_context_options'])) {
            $connection_params['stream_context_options'] = $params['stream_context_options'];
        }

        return $connection_params;
    }

    public function setConnectionParams($params)
    {
        if (!is_array($params)) {
            $params = array();
        }
        $merged = $params;
        $ip = '';
        if (array_key_exists('imap_provider', $merged) && $merged['imap_provider'] !== null && $merged['imap_provider'] !== '') {
            $ip = (string) $merged['imap_provider'];
        } else {
            $ip = (string) $this->getParam('imap_provider', 'custom');
        }
        if ($ip === '') {
            $ip = 'custom';
        }
        $merged['imap_provider'] = $ip;

        self::applyImapProviderPresetToParams($merged);

        $connection_params = array();
        foreach (array('email', 'login', 'server', 'port', 'password', 'imap_provider') as $key) {
            if (array_key_exists($key, $merged) && $merged[$key] !== null && $merged[$key] !== '') {
                $connection_params[$key] = $merged[$key];
            } else {
                $connection_params[$key] = $this->getParam($key);
            }
        }

        if ($ip === 'custom') {
            $connection_params['ssl'] = !empty($params['ssl']) ? 1 : 0;
        } else {
            $connection_params['ssl'] = !empty($merged['ssl']) ? 1 : 0;
        }

        // 1 = leave on server (UID tracking); 2 = delete after fetch (see settings template: hidden 2 + checkbox 1)
        $connection_params['leave_messages_on_server'] = (isset($params['leave_messages_on_server']) && (string) $params['leave_messages_on_server'] === '1') ? 1 : 0;

        $connection_params['skip_existing_on_create'] = (isset($params['skip_existing_on_create']) && (string) $params['skip_existing_on_create'] === '1') ? 1 : 0;

        $this->unsetParam('ssl');
        $this->setParams($connection_params, false);

        if (!$connection_params['leave_messages_on_server'] && !$connection_params['skip_existing_on_create']) {
            $this->deleteParam('last_imap_uid');
        }
    }

    public function saveConnectionParams($params)
    {
        if (!is_array($params)) {
            $params = array();
        }
        $merged = $params;
        $ip = '';
        if (array_key_exists('imap_provider', $merged) && $merged['imap_provider'] !== null && $merged['imap_provider'] !== '') {
            $ip = (string) $merged['imap_provider'];
        } else {
            $ip = (string) $this->getParam('imap_provider', 'custom');
        }
        if ($ip === '') {
            $ip = 'custom';
        }
        $merged['imap_provider'] = $ip;

        self::applyImapProviderPresetToParams($merged);

        $connection_params = array();
        foreach (array('email', 'login', 'server', 'port', 'password', 'imap_provider') as $key) {
            if (array_key_exists($key, $merged) && $merged[$key] !== null && $merged[$key] !== '') {
                $connection_params[$key] = $merged[$key];
            } else {
                $connection_params[$key] = $this->getParam($key);
            }
        }

        $connection_params['ssl'] = null;
        if ($ip === 'custom') {
            if (!empty($params['ssl'])) {
                $connection_params['ssl'] = 1;
            }
        } else {
            $connection_params['ssl'] = 1;
        }

        // 1 = leave on server (UID tracking); 2 = delete after fetch (see settings template: hidden 2 + checkbox 1)
        $connection_params['leave_messages_on_server'] = (isset($params['leave_messages_on_server']) && (string) $params['leave_messages_on_server'] === '1') ? 1 : 0;

        $connection_params['skip_existing_on_create'] = (isset($params['skip_existing_on_create']) && (string) $params['skip_existing_on_create'] === '1') ? 1 : 0;

        $this->saveParams($connection_params, false);

        if (!$connection_params['leave_messages_on_server'] && !$connection_params['skip_existing_on_create']) {
            $this->deleteParam('last_imap_uid');
        }
    }

    protected function tryToConnect(array $params)
    {
        $errors = array();
        try {
            if (!defined('OPENSSL_VERSION_NUMBER') && !empty($params['ssl'])) {
                $errors['ssl'] = _w('Encryption requires OpenSSL PHP module to be installed.');
            } else {
                $options = $params;
                $options['timeout'] = 20;
                $mail_reader = new crmImap4PluginMailReader($options);
                $mail_reader->count();
                $mail_reader->close();
            }
        } catch (Exception $e) {
            $err = $e->getMessage();
            if (!$err || $err == ' ()') {
                $err = _w('Unknown error');
            }
            $errors[''] = _w('An error occurred during an attempt to connect with specified settings.').' '.$err;
        }
        return $errors;
    }

    /**
     * Store last_imap_uid = current max UID when skip-existing is on and baseline is not set yet.
     * Called after successful source settings save (create or edit).
     */
    public function applyBaselineAfterCreateIfNeeded()
    {
        if ((int) $this->getParam('skip_existing_on_create', 0) !== 1) {
            return;
        }
        $last_uid = $this->getParam('last_imap_uid');
        if ($last_uid !== null && $last_uid !== '') {
            return;
        }
        $reader = null;
        try {
            $reader = new crmImap4PluginMailReader($this->getConnectionParams());
            $max = $reader->getMaxUid();
            $reader->close();
            $reader = null;
            $this->saveParam('last_imap_uid', (string) $max);
        } catch (Exception $e) {
            if ($reader) {
                try {
                    $reader->close();
                } catch (Exception $e2) {
                }
            }
            waLog::log('Imap4 baseline UID: '.$e->getMessage(), 'crm/imap4_source_email_worker.log');
        }
    }
}
