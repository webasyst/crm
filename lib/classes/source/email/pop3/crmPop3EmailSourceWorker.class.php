<?php

class crmPop3EmailSourceWorker extends crmEmailSourceWorker
{
    static protected $LOG_FILE = 'crm/pop3_source_email_worker.log';

    /**
     * @return waMailPOP3|null
     */
    protected function getMailReader()
    {
        if (array_key_exists('mail_reader', $this->cache)) {
            return $this->cache['mail_reader'];
        }
        try {
            $reader = new waMailPOP3($this->source->getConnectionParams());
            $this->cache['mail_reader'] = $reader;
        } catch (Exception $e) {
            $this->cache['mail_reader'] = null;
        }
        return $this->cache['mail_reader'];
    }

    public function isWorkToDo(array $process = array())
    {
        $mail_reader = $this->getMailReader();
        if (!$mail_reader) {
            return false;
        }
        $count = $mail_reader->count();
        $count = $count[0];
        return $count > 0;
    }

    protected function receiveMailMessages(array $process = array())
    {
        $mail_reader = $this->getMailReader();
        if (!$mail_reader) {
            return array();
        }

        $count = $mail_reader->count();
        $count = $count[0];
        $limit_count = min($count, 15);

        $temp_path = wa('crm')->getTempPath('mail', 'crm');

        $messages = array();

        for ($i_mail = 1; $i_mail <= $limit_count; $i_mail += 1) {

            $unique_id = uniqid(true);
            $mail_dir = $temp_path . '/' . $unique_id;
            waFiles::create($mail_dir);
            $mail_path = $mail_dir . '/mail.eml';

            // get mail by POP
            $mail_reader->get($i_mail, $mail_path);

            try {
                $message = new crmMailMessage($mail_path);
                $messages[$unique_id] = $message;
    
                // mark mail by POP as read
                $mail_reader->delete($i_mail);
            } catch (Exception $e) {
                $message = join(PHP_EOL, array(
                    'Exception',
                    $e->getMessage(),
                    $e->getTraceAsString()
                ));
                waLog::log($message, self::$LOG_FILE); 
            }
        }

        $mail_reader->close();

        return $messages;
    }
}
