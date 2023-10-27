<?php

/**
 * The Imap PHP class provides a wrapper for commonly used PHP IMAP functions.
 *
 * Original class by <Jeff Geerling>
 * @see https://github.com/geerlingguy/Imap
 *
 * Quick Start:
 * @code
 *   $mailbox = new crmMailIMAP($options);
 *   $mailbox->getMailboxInfo();
 */

class crmImapPluginMailReader
{
    protected $options = array(
        'port' => 993,
        'ssl' => false,
        'folder' => 'INBOX',
    );

    private $server;
    private $login;
    private $pass;
    private $port;
    private $folder;
    private $ssl;
    private $timeout;

    private $baseAddress;
    private $address;
    private $mailbox;

    /**
     * crmMailIMAP constructor.
     * @param array $options
     *   string 'server'  - The IMAP hostname. Example: imap.gmail.com;
     *   int    'port'    - Default: 933;
     *   string 'login'   - Login used for connection. Gmail uses full username@gmail.com,
     *                      but many providers simply use username;
     *   string 'pass'    - Account password;
     *   bool   'ssl'     - Default: false;
     *   string 'folder'  - IMAP Folder to open. Default: 'INBOX';
     * @throws waException
     */
    public function __construct($options)
    {
        foreach ($options as $k => $v) {
            $this->options[$k] = $v;
        }

        $this->server = $this->getOption('server');
        $this->login = $this->getOption('login');
        $this->pass = $this->getOption('password');
        $this->port = $this->getOption('port');
        $this->folder = $this->getOption('folder');
        $this->ssl = $this->getOption('ssl');

        $this->timeout = $this->getOption('timeout');
        if (!$this->timeout) {
            $this->timeout = 15;
        }

        if (empty($this->server) ||
            empty($this->login) ||
            empty($this->pass) ||
            empty($this->port)) {
                throw new waException("Error: All Constructor values require a non NULL input.");
        }

        $this->connect();
    }

    public function getOption($name, $default = null)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    /**
     * Log into an IMAP server.
     *
     * This method is called on the initialization of the class (see
     * __construct()), and whenever you need to log into a different account.
     *
     * @throws waException when IMAP can't connect.
     */
    public function connect()
    {
        $ssl = null;
        if ($this->ssl) {
            $ssl = "/ssl";
        }

        $baseAddress = '{' . $this->server . ':' . $this->port . '/imap'. $ssl . '}';

        $address = $baseAddress . $this->folder;

        // Set the new address and the base address.
        $this->baseAddress = $baseAddress;
        $this->address = $address;

        $error = null;
        $mailbox = $this->imapOpen($address, $error);

        if ($error) {
            throw new waException("Error: " . $error);
        }

        $this->mailbox = $mailbox;
    }

    protected function imapOpen($address, &$error = null)
    {
        imap_timeout(IMAP_OPENTIMEOUT, $this->timeout);
        imap_timeout(IMAP_CLOSETIMEOUT, $this->timeout);
        imap_timeout(IMAP_READTIMEOUT, $this->timeout);
        imap_timeout(IMAP_WRITETIMEOUT, $this->timeout);

        $result = @imap_open($address, $this->login, $this->pass);
        if (!$result) {
            $error = imap_last_error();
        }

        // clear errors and alerts(notices) from buffer, so they won't be thrown to php stdout
        imap_errors();
        imap_alerts();

        return $result;
    }


    /**
     * Return the number of messages in the box and the total size of those messages in bytes
     * (for the active $this->folder)
     *
     * @return array($number, $size)
     */
    public function count()
    {
        $data = imap_mailboxmsginfo($this->mailbox);
        if ($data) {
            return array($data->Nmsgs, $data->Size);
        }
        return array(0, 0);
    }

    /**
     * Returns an associative array with email subjects and message ids for all
     * messages in the active $this->folder.
     *
     * @return array with message id as key and subject as value.
     */
    public function getIds()
    {
        $this->tickle();

        // Fetch overview of mailbox.
        $number_messages = imap_num_msg($this->mailbox);
        if ($number_messages) {
            $overviews = imap_fetch_overview($this->mailbox, "1:" . imap_num_msg($this->mailbox), 0);
        } else {
            $overviews = array();
        }
        $ids = array();

        // Loop through message overviews, build message array.
        foreach ($overviews as $overview) {
            $ids[$overview->msgno] = $overview->subject;
        }

        return $ids;
    }

    /**
     * Returns an raw with detailed information about a given message.
     *
     * @param int $message_id
     * @param bool $file
     * @return string
     *
     * @throws waException when message with given id can't be found.
     */
    public function get($message_id, $file = false)
    {
        $this->tickle();

        // Get message details.
        $details = imap_headerinfo($this->mailbox, $message_id);
        if ($details) {
            // Get the raw headers.
            $raw_header = imap_fetchheader($this->mailbox, $message_id);

            // Get the message body.
            $body = imap_fetchbody($this->mailbox, $message_id, 1.2);
            if (!strlen($body) > 0) {
                $body = imap_fetchbody($this->mailbox, $message_id, 1);
            }

            // Get the message body encoding.
            $encoding = $this->getEncodingType($message_id);

            // Decode body into plaintext (8bit, 7bit, and binary are exempt).
            if ($encoding == 'BASE64') {
                $body = $this->decodeBase64($body);
            } elseif ($encoding == 'QUOTED-PRINTABLE') {
                $body = $this->decodeQuotedPrintable($body);
            } elseif ($encoding == '8BIT') {
                $body = $this->decode8Bit($body);
            } elseif ($encoding == '7BIT') {
                $body = $this->decode7Bit($body);
            }
        } else {
            throw new waException("Message could not be found: ".imap_last_error());
        }

        // Raw the message.
        $message = $raw_header . $body;

        if ($file) {
            $fh = @fopen($file, "w+");
            if (!$fh) {
                throw new waException("Cannot open file ".$file);
            }
            fwrite($fh, $message);
            fclose($fh);
            return $file;
        }

        return $message;
    }

    /**
     * Delete message by $message_id.
     *
     * @param int $message_id
     *   Message id.
     * @param bool $immediate
     *   Set TRUE if message should be deleted immediately. Otherwise, message
     *   will not be deleted until close() is called. Normally, this is a
     *   bad idea, as other message ids will change if a message is deleted.
     *
     * @return (empty)
     *
     * @throws waException when message can't be deleted.
     */
    public function delete($message_id, $immediate = TRUE)
    {
        $this->tickle();

        // Mark message for deletion.
        if (!imap_delete($this->mailbox, $message_id)) {
            throw new waException("Message could not be deleted: " . imap_last_error());
        }

        // Immediately delete the message if $immediate is TRUE.
        if ($immediate) {
            imap_expunge($this->mailbox);
        }
    }

    /**
     * Moves an email into the given mailbox.
     *
     * @param int $message_id
     * @param string $folder
     *   The name of the folder (mailbox) into which messages should be moved.
     *   $folder could either be the folder name or 'INBOX.foldername'.
     *
     * @return bool
     *   Returns TRUE on success, FALSE on failure.
     */
    public function moveMessage($message_id, $folder)
    {
        $message_range = $message_id . ':' . $message_id;
        return imap_mail_move($this->mailbox, $message_range, $folder);
    }

    /**
     * Change IMAP folders and reconnect to the server.
     *
     * @param $folder_name
     *   The name of the folder to change to.
     */
    public function changeFolder($folder_name)
    {
        if ($this->ssl) {
            $address = '{' . $this->server . ':' . $this->port . '/imap/ssl}' . $folder_name;
        } else {
            $address = '{' . $this->server . ':' . $this->port . '/imap}' . $folder_name;
        }

        $this->address = $address;
        $this->reconnect();
    }

    /**
     * Return an associative array containing the number of recent, unread, and
     * total messages.
     *
     * @return array with keys:
     *   unread
     *   recent
     *   total
     */
    public function status()
    {
        $this->tickle();

        // Get general mailbox information.
        $info = imap_status($this->mailbox, $this->address, SA_ALL);
        $mail_info = array(
            'unread' => $info->unseen,
            'recent' => $info->recent,
            'total' => $info->messages,
        );
        return $mail_info;
    }

    /**
     * Return an array of objects containing mailbox information.
     *
     * @return array of mailbox names.
     */
    public function getMailboxInfo()
    {
        $this->tickle();

        // Get all mailbox information.
        $mailboxInfo = imap_getmailboxes($this->mailbox, $this->baseAddress, '*');
        $mailboxes = array();
        foreach ($mailboxInfo as $mailbox) {
            // Remove baseAddress from mailbox name.
            $mailboxes[] = array(
                'mailbox' => $mailbox->name,
                'name' => str_replace($this->baseAddress, '', $mailbox->name),
            );
        }

        return $mailboxes;
    }

    /**
     * Decodes Base64-encoded text.
     *
     * @param string $text
     *   Base64 encoded text to convert.
     *
     * @return string
     *   Decoded text.
     */
    public function decodeBase64($text)
    {
        $this->tickle();
        return imap_base64($text);
    }

    /**
     * Decodes quoted-printable text.
     *
     * @param string $text
     *   Quoted printable text to convert.
     *
     * @return string
     *   Decoded text.
     */
    public function decodeQuotedPrintable($text)
    {
        return quoted_printable_decode($text);
    }

    /**
     * Decodes 8-Bit text.
     *
     * @param string $text
     *   8-Bit text to convert.
     *
     * @return string
     *   Decoded text.
     */
    public function decode8Bit($text)
    {
        return quoted_printable_decode(imap_8bit($text));
    }

    /**
     * Decodes 7-Bit text.
     *
     * PHP seems to think that most emails are 7BIT-encoded, therefore this
     * decoding method assumes that text passed through may actually be base64-
     * encoded, quoted-printable encoded, or just plain text. Instead of passing
     * the email directly through a particular decoding function, this method
     * runs through a bunch of common encoding schemes to try to decode everything
     * and simply end up with something *resembling* plain text.
     *
     * Results are not guaranteed, but it's pretty good at what it does.
     *
     * @param string $text
     *   7-Bit text to convert.
     *
     * @return string
     *   Decoded text.
     */
    public function decode7Bit($text)
    {
        // If there are no spaces on the first line, assume that the body is
        // actually base64-encoded, and decode it.
        $lines = explode("\r\n", $text);
        $first_line_words = explode(' ', $lines[0]);
        if ($first_line_words[0] == $lines[0]) {
            $text = base64_decode($text);
        }

        // Manually convert common encoded characters into their UTF-8 equivalents.
        $characters = array(
            '=20' => ' ', // space.
            '=2C' => ',', // comma.
            '=E2=80=99' => "'", // single quote.
            '=0A' => "\r\n", // line break.
            '=0D' => "\r\n", // carriage return.
            '=A0' => ' ', // non-breaking space.
            '=B9' => '$sup1', // 1 superscript.
            '=C2=A0' => ' ', // non-breaking space.
            "=\r\n" => '', // joined line.
            '=E2=80=A6' => '&hellip;', // ellipsis.
            '=E2=80=A2' => '&bull;', // bullet.
            '=E2=80=93' => '&ndash;', // en dash.
            '=E2=80=94' => '&mdash;', // em dash.
        );

        // Loop through the encoded characters and replace any that are found.
        foreach ($characters as $key => $value) {
            $text = str_replace($key, $value, $text);
        }

        return $text;
    }

    /**
     * Strips quotes (older messages) from a message body.
     *
     * This function removes any lines that begin with a quote character (>).
     * Note that quotes in reply bodies will also be removed by this function,
     * so only use this function if you're okay with this behavior.
     *
     * @param string $message
     *   The message to be cleaned.
     * @param bool $plain_text_output
     *   Set to TRUE to also run the text through strip_tags() (helpful for
     *   cleaning up HTML emails).
     *
     * @return string
     *   Same as message passed in, but with all quoted text removed.
     *
     * @see http://stackoverflow.com/a/12611562/100134
     */
    public function cleanReplyEmail($message, $plain_text_output = FALSE)
    {
        // Strip markup if $plain_text_output is set.
        if ($plain_text_output) {
            $message = strip_tags($message);
        }

        // Remove quoted lines (lines that begin with '>').
        $message = preg_replace("/(^\w.+:\n)?(^>.*(\n|$))+/mi", '', $message);

        // Remove lines beginning with 'On' and ending with 'wrote:' (matches
        // Mac OS X Mail, Gmail).
        $message = preg_replace("/^(On).*(wrote:).*$/sm", '', $message);

        // Remove lines like '----- Original Message -----' (some other clients).
        // Also remove lines like '--- On ... wrote:' (some other clients).
        $message = preg_replace("/^---.*$/mi", '', $message);

        // Remove lines like '____________' (some other clients).
        $message = preg_replace("/^____________.*$/mi", '', $message);

        // Remove blocks of text with formats like:
        //   - 'From: Sent: To: Subject:'
        //   - 'From: To: Sent: Subject:'
        //   - 'From: Date: To: Reply-to: Subject:'
        $message = preg_replace("/From:.*^(To:).*^(Subject:).*/sm", '', $message);

        // Remove any remaining whitespace.
        $message = trim($message);

        return $message;
    }

    /**
     * Takes in a string of email addresses and returns an array of addresses
     * as objects. For example, passing in 'John Doe <johndoe@sample.com>'
     * returns the following array:
     *
     *     Array (
     *       [0] => stdClass Object (
     *         [mailbox] => johndoe
     *         [host] => sample.com
     *         [personal] => John Doe
     *       )
     *     )
     *
     * You can pass in a string with as many addresses as you'd like, and each
     * address will be parsed into a new object in the returned array.
     *
     * @param string $addresses
     *   String of one or more email addresses to be parsed.
     *
     * @return array
     *   Array of parsed email addresses, as objects.
     *
     * @see imap_rfc822_parse_adrlist().
     */
    public function parseAddresses($addresses)
    {
        return imap_rfc822_parse_adrlist($addresses, '#');
    }

    /**
     * Create an email address to RFC822 specifications.
     *
     * @param string $username
     *   Name before the @ sign in an email address (example: 'johndoe').
     * @param string $host
     *   Address after the @ sign in an email address (example: 'sample.com').
     * @param string $name
     *   Name of the entity (example: 'John Doe').
     *
     * @return string Email Address in the following format:
     *  'John Doe <johndoe@sample.com>'
     */
    public function createAddress($username, $host, $name)
    {
        return imap_rfc822_write_address($username, $host, $name);
    }

    /**
     * Returns structured information for a given message id.
     *
     * @param $message_id
     *   Message id for which structure will be returned.
     *
     * @return object
     *   See imap_fetchstructure() return values for details.
     *
     * @see imap_fetchstructure().
     */
    public function getStructure($message_id)
    {
        return imap_fetchstructure($this->mailbox, $message_id);
    }

    /**
     * Returns the primary body type for a given message id.
     *
     * @param int $message_id
     *   Message id.
     * @param bool $numeric
     *   Set to true for a numerical body type.
     *
     * @return mixed
     *   Integer value of body type if numeric, string if not numeric.
     */
    public function getBodyType($message_id, $numeric = false)
    {
        // See imap_fetchstructure() documentation for explanation.
        $types = array(
            0 => 'Text',
            1 => 'Multipart',
            2 => 'Message',
            3 => 'Application',
            4 => 'Audio',
            5 => 'Image',
            6 => 'Video',
            7 => 'Other',
        );

        // Get the structure of the message.
        $structure = $this->getStructure($message_id);

        // Return a number or a string, depending on the $numeric value.
        if ($numeric) {
            return $structure->type;
        } else {
            return $types[$structure->type];
        }
    }

    /**
     * Returns the encoding type of a given $messageId.
     *
     * @param int $message_id
     * @param bool $numeric
     *   Set to true for a numerical encoding type.
     *
     * @return mixed
     *   Integer value of body type if numeric, string if not numeric.
     */
    public function getEncodingType($message_id, $numeric = false)
    {
        // See imap_fetchstructure() documentation for explanation.
        $encodings = array(
            0 => '7BIT',
            1 => '8BIT',
            2 => 'BINARY',
            3 => 'BASE64',
            4 => 'QUOTED-PRINTABLE',
            5 => 'OTHER',
        );

        // Get the structure of the message.
        $structure = $this->getStructure($message_id);

        // Return a number or a string, depending on the $numeric value.
        if ($numeric) {
            return $structure->encoding;
        } else {
            return $encodings[$structure->encoding];
        }
    }

    /**
     * Closes an active IMAP connection.
     *
     * @return (empty)
     */
    public function close()
    {
        // Close the connection, deleting all messages marked for deletion.
        imap_close($this->mailbox, CL_EXPUNGE);
    }

    /**
     * Reconnect to the IMAP server.
     *
     * @return (empty)
     *
     * @throws waException when IMAP can't reconnect.
     */
    private function reconnect()
    {
        $this->mailbox = imap_open($this->address, $this->login, $this->pass);
        if (!$this->mailbox) {
            throw new waException("Reconnection Failure: " . imap_last_error());
        }
    }

    /**
     * Checks to see if the connection is alive. If not, reconnects to server.
     *
     * @return (empty)
     */
    private function tickle()
    {
        if (!imap_ping($this->mailbox)) {
            $this->reconnect();
        }
    }

    /**
     * Determines whether the given message is from an auto-responder.
     *
     * This method checks whether the header contains any auto response headers as
     * outlined in RFC 3834, and also checks to see if the subject line contains
     * certain strings set by different email providers to indicate an automatic
     * response.
     *
     * @see http://tools.ietf.org/html/rfc3834
     *
     * @param string $header
     *   Message header as returned by imap_fetchheader().
     *
     * @return bool
     *   TRUE if this message comes from an autoresponder.
     */
    private function detectAutoresponder($header)
    {
        $autoresponder_strings = array(
            'X-Autoresponse:', // Other email servers.
            'X-Autorespond:', // LogSat server.
            'Subject: Auto Response', // Yahoo mail.
            'Out of office', // Generic.
            'Out of the office', // Generic.
            'out of the office', // Generic.
            'Auto-reply', // Generic.
            'Autoreply', // Generic.
            'autoreply', // Generic.
        );

        // Check for presence of different autoresponder strings.
        foreach ($autoresponder_strings as $string) {
            if (strpos($header, $string) !== false) {
                return true;
            }
        }

        return false;
    }

}
