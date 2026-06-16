<?php

/**
 * IMAP4rev1 client for the CRM IMAP4 source plugin only (not part of wa-system).
 * Uses PHP’s imap extension when available, otherwise plain sockets.
 * API mirrors waMailPOP3 where practical: count(), getIds(), get(), delete(), close().
 *
 * Options (same idea as waMailPOP3):
 * - server (host), port (default 993 ssl / 143 plain), ssl, tls, timeout, read_timeout
 * - user | login, password
 * - mailbox — folder name, default INBOX
 * - use_uid — if true, get()/delete() use UID instead of sequence numbers
 * - stream_context_options — passed to stream_socket_client (socket backend only)
 * - php_imap — bool, default true: if false, always use socket backend (even when ext-imap is loaded)
 * - ssl_novalidate — bool, for PHP imap backend append /novalidate-cert to the SSL mailbox path
 * - imap_open_retries — int, retries passed to imap_open() (default 0, same as PHP)
 *
 * searchUidsSince() filters UIDs from FETCH 1:* (UID) strictly greater than $since_uid.
 */
class crmImap4PluginMailImap
{
    const BACKEND_SOCKET = 'socket';
    const BACKEND_PHP_IMAP = 'php_imap';

    protected $options = array(
        'mailbox' => 'INBOX',
        'use_uid' => false,
        'php_imap' => true,
    );

    protected $server;
    protected $port;
    protected $user;
    protected $password;

    /** @var string */
    protected $backend = self::BACKEND_SOCKET;

    /** @var resource|null socket stream (socket backend) */
    protected $handler;

    /** @var resource|null imap stream (PHP imap backend) */
    protected $imapStream;

    /** @var int */
    protected $tagCounter = 0;

    public function __construct($options)
    {
        foreach ($options as $k => $v) {
            $this->options[$k] = $v;
        }

        if (!$this->getOption('port')) {
            $this->options['port'] = $this->getOption('ssl') ? 993 : 143;
        }

        $this->user = $this->getOption('user');
        if (!$this->user) {
            $this->user = $this->getOption('login');
        }
        $this->password = $this->getOption('password');

        if ($this->shouldUsePhpImapBackend()) {
            $this->backend = self::BACKEND_PHP_IMAP;
            $this->connectPhpImapBackend();
        } else {
            $this->backend = self::BACKEND_SOCKET;
            $this->server = ($this->getOption('ssl') ? 'ssl://' : '').$this->getOption('server');
            $this->port = $this->getOption('port');
            $this->connect();
        }
    }

    /**
     * @return bool
     */
    protected function shouldUsePhpImapBackend()
    {
        if (!$this->getOption('php_imap', true)) {
            return false;
        }
        return function_exists('imap_open');
    }

    /**
     * Mailbox reference for imap_open(), e.g. {host:993/imap/ssl}INBOX
     *
     * @return string
     */
    protected function buildPhpImapMailboxRef()
    {
        $host = $this->getOption('server');
        $port = (int) $this->getOption('port');
        $mailbox = $this->getOption('mailbox', 'INBOX');

        if ($this->getOption('ssl')) {
            $transport = '/imap/ssl';
        } elseif ($this->getOption('tls')) {
            $transport = '/imap/tls';
        } else {
            $transport = '/imap/notls';
        }
        if ($this->getOption('ssl_novalidate')) {
            $transport .= '/novalidate-cert';
        }

        return '{'.$host.':'.$port.$transport.'}'.$mailbox;
    }

    /**
     * @throws waException
     */
    protected function connectPhpImapBackend()
    {
        if (function_exists('imap_errors')) {
            imap_errors();
        }
        if (function_exists('imap_alerts')) {
            imap_alerts();
        }

        $ref = $this->buildPhpImapMailboxRef();
        $retries = (int) $this->getOption('imap_open_retries', 0);
        if ($retries < 0) {
            $retries = 0;
        }

        $this->imapStream = @imap_open($ref, (string) $this->user, (string) $this->password, 0, $retries);
        if (!$this->imapStream) {
            $err = function_exists('imap_last_error') ? imap_last_error() : '';
            if (!$err) {
                $err = _ws('Could not connect to IMAP server');
            }
            throw new waException($err);
        }
    }

    public function getOption($name, $default = null)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    public function connect()
    {
        if ($this->backend === self::BACKEND_PHP_IMAP) {
            $this->close();
            $this->connectPhpImapBackend();
            return;
        }

        $error = '';
        if (!$this->server) {
            $error = _ws('Server address is required');
        } elseif (!$this->port || !wa_is_int($this->port)) {
            $error = _ws('Port is required');
        }
        if ($error) {
            throw new waException($error);
        }
        $this->tryToConnect();
    }

    protected function tryToConnect()
    {
        $stream_context_options = $this->getOption('stream_context_options');

        if ($this->getOption('tls')) {

            $remote_socket = 'tcp://'.$this->getOption('server').':'.$this->port;
            $timeout = $this->getOption('timeout', 10);

            if ($stream_context_options) {
                $stream_context = stream_context_create($stream_context_options);
                $this->handler = stream_socket_client($remote_socket, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $stream_context);
            } else {
                $this->handler = @stream_socket_client($remote_socket, $errno, $errstr, $timeout);
            }
            if ($this->handler) {
                $this->setStreamReadTimeout();
                $welcome = $this->readLine();
                if ($welcome === false || $welcome === '') {
                    throw new waException('IMAP read failed (welcome): '.$this->explainReadFailure());
                }
                $this->sendTagged('STARTTLS');
                if (!stream_socket_enable_crypto($this->handler, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new waException('IMAP STARTTLS failed for '.$this->server);
                }
            }

        } else {

            $timeout = $this->getOption('timeout', 10);

            if ($stream_context_options) {
                $remote_socket = $this->server.':'.$this->port;
                $stream_context = stream_context_create($stream_context_options);
                $this->handler = @stream_socket_client($remote_socket, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $stream_context);
            } else {
                $this->handler = @fsockopen($this->server, $this->port, $errno, $errstr, $timeout);
            }

            if ($this->handler) {
                $this->setStreamReadTimeout();
                $welcome = $this->readLine();
                if ($welcome === false || $welcome === '') {
                    throw new waException('IMAP read failed (welcome): '.$this->explainReadFailure());
                }
            }
        }

        if ($this->handler) {
            $this->login();
            $this->selectMailbox();
        } else {

            if (!$errno && !$errstr) {
                if (function_exists('socket_last_error')) {
                    $errno = socket_last_error();
                    $errstr = socket_strerror($errno);
                } else {
                    $error = error_get_last();
                    $errstr = is_array($error) && isset($error['message']) ? $error['message'] : '';
                }
            }

            if (!preg_match('//u', $errstr)) {
                $tmp = @iconv('windows-1251', 'utf-8//ignore', $errstr);
                if ($tmp) {
                    $errstr = $tmp;
                }
            }
            throw new waException($errstr.' ('.$errno.')', $errno);
        }
    }

    /**
     * Applies stream read timeout. Option read_timeout: seconds, or 0 to skip. Default: max(120, timeout).
     */
    protected function setStreamReadTimeout()
    {
        $read_timeout = $this->getOption('read_timeout');
        if ($read_timeout === null) {
            $read_timeout = max(120, (int) $this->getOption('timeout', 10));
        } else {
            $read_timeout = (int) $read_timeout;
        }
        if ($read_timeout > 0) {
            stream_set_timeout($this->handler, $read_timeout, 0);
        }
    }

    /**
     * @return string
     */
    protected function explainReadFailure()
    {
        if (!$this->handler) {
            return 'no connection';
        }
        $meta = @stream_get_meta_data($this->handler);
        if (!empty($meta['timed_out'])) {
            return 'read timed out';
        }
        if (@feof($this->handler)) {
            return 'connection closed';
        }
        return 'no data received';
    }

    protected function nextTag()
    {
        $this->tagCounter += 1;
        return 'A'.$this->tagCounter;
    }

    /**
     * @param string $command arguments without tag (e.g. 'LOGIN "u" "p"')
     * @return string full server output until tagged line
     */
    protected function sendTagged($command)
    {
        $tag = $this->nextTag();
        $line = $tag.' '.$command."\r\n";
        if (false === fwrite($this->handler, $line)) {
            throw new waException('Cannot write to '.$this->server);
        }
        return $this->readUntilTagged($tag);
    }

    /**
     * Read lines and literals until $tag OK|NO|BAD.
     *
     * @param string $tag
     * @return string
     */
    protected function readUntilTagged($tag)
    {
        $buffer = '';
        $tag_re = '/^'.preg_quote($tag, '/').'\s+(OK|NO|BAD)\b/i';

        while (true) {
            $line = $this->readLine();
            if ($line === false || $line === '') {
                throw new waException('IMAP read failed: '.$this->explainReadFailure());
            }
            $buffer .= $line;

            if (preg_match('/\{(\d+)\}\r?\n$/', $line, $m)) {
                $buffer .= $this->readExact((int) $m[1]);
                continue;
            }

            if (preg_match($tag_re, $line, $m)) {
                $status = strtoupper($m[1]);
                if ($status === 'OK') {
                    return $buffer;
                }
                throw new waException(sprintf_wp('IMAP error from %s: %s', $this->server, trim($line)));
            }
        }
    }

    /**
     * @param int $n bytes
     * @return string
     */
    protected function readExact($n)
    {
        $buf = '';
        $got = 0;
        while ($got < $n) {
            $part = fread($this->handler, $n - $got);
            if ($part === false || $part === '') {
                throw new waException('IMAP read failed (literal): '.$this->explainReadFailure());
            }
            $buf .= $part;
            $got += strlen($part);
        }
        return $buf;
    }

    /**
     * @return string
     */
    protected function readLine()
    {
        return fgets($this->handler);
    }

    /**
     * Quote atom or string for IMAP.
     *
     * @param string $s
     * @return string
     */
    protected function quoteAuthArg($s)
    {
        if (preg_match('/^[A-Za-z0-9&._-]+$/', $s)) {
            return $s;
        }
        return '"'.str_replace(array('\\', '"'), array('\\\\', '\\"'), $s).'"';
    }

    protected function login()
    {
        $u = $this->quoteAuthArg($this->user);
        $p = $this->quoteAuthArg($this->password);
        $this->sendTagged("LOGIN $u $p");
    }

    protected function selectMailbox()
    {
        $mb = $this->quoteAuthArg($this->getOption('mailbox', 'INBOX'));
        $this->sendTagged("SELECT $mb");
    }

    /**
     * @return array($number, $size) message count and approximate total size (bytes) if supported
     */
    public function count()
    {
        if ($this->backend === self::BACKEND_PHP_IMAP) {
            $n = imap_num_msg($this->imapStream);
            return array($n !== false ? (int) $n : 0, 0);
        }

        $mb = $this->quoteAuthArg($this->getOption('mailbox', 'INBOX'));
        // MESSAGES only: SIZE in STATUS is an optional extension (not all servers support it).
        $out = $this->sendTagged("STATUS $mb (MESSAGES)");

        $messages = 0;
        if (preg_match('/MESSAGES\s+(\d+)/i', $out, $m)) {
            $messages = (int) $m[1];
        }

        return array($messages, 0);
    }

    /**
     * Return sorted list of UIDs strictly greater than $since_uid.
     * Uses FETCH 1:* (UID) so results match what UID FETCH will retrieve (avoids UID SEARCH quirks).
     *
     * @param int|string $since_uid last processed UID; use 0 to list all UIDs in mailbox
     * @return int[] ascending
     */
    public function searchUidsSince($since_uid)
    {
        $since_uid = (int) $since_uid;
        if ($since_uid < 0) {
            $since_uid = 0;
        }
        $map = $this->getIds();
        $uids = array();
        foreach ($map as $seq => $uid) {
            $uid = (int) $uid;
            if ($uid > $since_uid) {
                $uids[] = $uid;
            }
        }
        $uids = array_unique($uids);
        sort($uids, SORT_NUMERIC);
        return array_values($uids);
    }

    /**
     * Highest UID in the selected mailbox (from FETCH 1:* (UID)); 0 if empty.
     *
     * @return int
     */
    public function getMaxUid()
    {
        $map = $this->getIds();
        if (!$map) {
            return 0;
        }
        $max = 0;
        foreach ($map as $uid) {
            $uid = (int) $uid;
            if ($uid > $max) {
                $max = $uid;
            }
        }
        return $max;
    }

    /**
     * @return array sequence number => UID string (same shape as waMailPOP3::getIds)
     */
    public function getIds()
    {
        if ($this->backend === self::BACKEND_PHP_IMAP) {
            $n = imap_num_msg($this->imapStream);
            if ($n === false || $n < 1) {
                return array();
            }
            $result = array();
            for ($i = 1; $i <= (int) $n; $i++) {
                $uid = imap_uid($this->imapStream, $i);
                if ($uid !== false) {
                    $result[$i] = (string) $uid;
                }
            }
            return $result;
        }

        $out = $this->sendTagged('FETCH 1:* (UID)');
        $result = array();
        if (preg_match_all('/\*\s+(\d+)\s+FETCH\s+\(\s*UID\s+(\d+)\s*\)/i', $out, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $result[$m[1]] = $m[2];
            }
        }
        return $result;
    }

    /**
     * Fetch message by sequence number or UID (see use_uid).
     *
     * @param int|string $id
     * @param bool|string $file path to write .eml or false to return string
     * @return string|false
     */
    public function get($id, $file = false)
    {
        $use_uid = $this->getOption('use_uid', false);
        $id_arg = $use_uid ? preg_replace('/\D/', '', (string) $id) : (string) (int) $id;
        if ($id_arg === '') {
            throw new waException('Invalid IMAP message id');
        }

        if ($this->backend === self::BACKEND_PHP_IMAP) {
            $ft_peek = defined('FT_PEEK') ? FT_PEEK : 8;
            $ft_uid = defined('FT_UID') ? FT_UID : 128;
            $flags = $ft_peek;
            if ($use_uid) {
                $flags |= $ft_uid;
            }
            $body = imap_body($this->imapStream, $id_arg, $flags);
            if ($body === false) {
                $err = function_exists('imap_last_error') ? imap_last_error() : '';
                throw new waException($err ? $err : 'imap_body failed');
            }
            if ($file) {
                if (false === @file_put_contents($file, $body)) {
                    throw new waException('Cannot open file '.$file);
                }
                return $file;
            }
            return $body;
        }

        $cmd = $use_uid ? "UID FETCH $id_arg (BODY.PEEK[])" : "FETCH $id_arg (BODY.PEEK[])";

        $tag = $this->nextTag();
        if (false === fwrite($this->handler, $tag.' '.$cmd."\r\n")) {
            throw new waException('Cannot write to '.$this->server);
        }

        $fh = null;
        if ($file) {
            $fh = @fopen($file, 'w+');
            if (!$fh) {
                throw new waException('Cannot open file '.$file);
            }
        }

        $body = '';
        $tag_re = '/^'.preg_quote($tag, '/').'\s+(OK|NO|BAD)\b/i';

        try {
            while (true) {
                $line = $this->readLine();
                if ($line === false || $line === '') {
                    throw new waException('IMAP read failed (FETCH): '.$this->explainReadFailure());
                }

                if (preg_match('/\{(\d+)\}\r?\n$/', $line, $m)) {
                    $n = (int) $m[1];
                    if ($fh) {
                        $left = $n;
                        while ($left > 0) {
                            $part = fread($this->handler, min(65536, $left));
                            if ($part === false || $part === '') {
                                throw new waException('IMAP read failed (FETCH body): '.$this->explainReadFailure());
                            }
                            fwrite($fh, $part);
                            $left -= strlen($part);
                        }
                    } else {
                        $body .= $this->readExact($n);
                    }
                    continue;
                }

                if (preg_match($tag_re, $line, $m)) {
                    $status = strtoupper($m[1]);
                    if ($status !== 'OK') {
                        throw new waException('IMAP FETCH failed: '.trim($line));
                    }
                    break;
                }
            }
        } catch (Exception $e) {
            if ($fh) {
                fclose($fh);
            }
            throw $e;
        }

        if ($fh) {
            fclose($fh);
            return $file;
        }
        return $body;
    }

    /**
     * Mark message deleted and expunge mailbox (IMAP semantics).
     *
     * @param int|string $id
     * @return string
     */
    public function delete($id)
    {
        $use_uid = $this->getOption('use_uid', false);
        $id_arg = $use_uid ? preg_replace('/\D/', '', (string) $id) : (string) (int) $id;
        if ($id_arg === '') {
            throw new waException('Invalid IMAP message id');
        }

        if ($this->backend === self::BACKEND_PHP_IMAP) {
            $ft_uid = defined('FT_UID') ? FT_UID : 128;
            $flags = $use_uid ? $ft_uid : 0;
            if (!imap_delete($this->imapStream, $id_arg, $flags)) {
                $err = function_exists('imap_last_error') ? imap_last_error() : '';
                throw new waException($err ? $err : 'imap_delete failed');
            }
            imap_expunge($this->imapStream);
            return '';
        }

        if ($use_uid) {
            $this->sendTagged("UID STORE $id_arg +FLAGS (\\Deleted)");
        } else {
            $this->sendTagged("STORE $id_arg +FLAGS (\\Deleted)");
        }
        $this->sendTagged('EXPUNGE');
        return '';
    }

    public function close()
    {
        if ($this->backend === self::BACKEND_PHP_IMAP && $this->imapStream) {
            @imap_close($this->imapStream, 0);
            $this->imapStream = null;
            return;
        }

        try {
            if ($this->handler) {
                $this->sendTagged('LOGOUT');
            }
        } catch (Exception $e) {
            // ignore
        }
        if ($this->handler) {
            fclose($this->handler);
            $this->handler = null;
        }
    }
}
