<?php

/**
 * Thin adapter: CRM connection params → {@link crmImap4PluginMailImap} (PHP imap extension when available; else sockets).
 */
class crmImap4PluginMailReader
{
    /** @var crmImap4PluginMailImap */
    private $mail;

    public function __construct($options)
    {
        $this->mail = new crmImap4PluginMailImap($this->mapOptions($options));
    }

    /**
     * @param array $options keys: server, port, password, login, ssl, tls, folder, stream_context_options
     * @return array
     */
    protected function mapOptions($options)
    {
        $mapped = array(
            'server' => (string) ifset($options['server']),
            'port' => (int) ifset($options['port'], 993),
            'password' => (string) ifset($options['password']),
            'login' => (string) ifset($options['login']),
            'timeout' => 15,
            'read_timeout' => 600,
        );
        if (!empty($options['ssl'])) {
            $mapped['ssl'] = 1;
        }
        if (!empty($options['tls'])) {
            $mapped['tls'] = 1;
        }
        if (!empty($options['folder'])) {
            $mapped['mailbox'] = (string) $options['folder'];
        }
        if (isset($options['stream_context_options']) && is_array($options['stream_context_options'])) {
            $mapped['stream_context_options'] = $options['stream_context_options'];
        }
        if (!empty($options['leave_messages_on_server']) || !empty($options['skip_existing_on_create'])) {
            $mapped['use_uid'] = true;
        } else {
            $mapped['use_uid'] = false;
        }
        return $mapped;
    }

    /**
     * @param int|string $since_uid
     * @return int[]
     */
    public function searchUidsSince($since_uid)
    {
        return $this->mail->searchUidsSince($since_uid);
    }

    /**
     * @return int
     */
    public function getMaxUid()
    {
        return $this->mail->getMaxUid();
    }

    /**
     * @param int|string $id sequence or UID depending on use_uid
     */
    public function delete($id)
    {
        $this->mail->delete($id);
    }

    /**
     * @return array ($messageCount, $totalSize) — size is 0 from STATUS without SIZE extension
     */
    public function count()
    {
        return $this->mail->count();
    }

    /**
     * @param int|string $id sequence number (default) or UID when use_uid is set on crmImap4PluginMailImap
     * @param bool|string $file
     * @return string|false
     */
    public function get($id, $file = false)
    {
        return $this->mail->get($id, $file);
    }

    public function close()
    {
        $this->mail->close();
    }
}
