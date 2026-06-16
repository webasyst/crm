<?php

class crmImap4PluginEmailSourceWorker extends crmEmailSourceWorker
{
    static protected $LOG_FILE = 'crm/imap4_source_email_worker.log';

    const BATCH_LIMIT = 50;

    /** @var crmSourceParamsModel|null */
    private $source_params_model;

    /**
     * @return crmSourceParamsModel
     */
    protected function getSourceParamsModel()
    {
        if ($this->source_params_model === null) {
            $this->source_params_model = new crmSourceParamsModel();
        }
        return $this->source_params_model;
    }

    /**
     * @return crmImap4PluginMailReader|null
     */
    protected function getMailReader()
    {
        if (array_key_exists('mail_reader', $this->cache)) {
            return $this->cache['mail_reader'];
        }
        try {
            $this->cache['mail_reader'] = new crmImap4PluginMailReader($this->source->getConnectionParams());
        } catch (Exception $e) {
            $this->cache['mail_reader'] = null;
        }
        return $this->cache['mail_reader'];
    }

    /**
     * @return bool
     */
    protected function isLeaveMessagesOnServer()
    {
        return (int) $this->source->getParam('leave_messages_on_server', 1) === 1;
    }

    /**
     * load_existing_on_create off → skip existing mail on first check.
     *
     * @return bool
     */
    protected function isSkipExistingOnCreate()
    {
        return (int) $this->source->getParam('skip_existing_on_create', 0) === 1;
    }

    /**
     * UID-based fetch when leaving mail on server or when existing mail must be skipped.
     *
     * @return bool
     */
    protected function shouldUseUidWorkflow()
    {
        return $this->isLeaveMessagesOnServer() || $this->isSkipExistingOnCreate();
    }

    public function isWorkToDo(array $process = array())
    {
        $mail_reader = $this->getMailReader();
        if (!$mail_reader) {
            return parent::isWorkToDo($process);
        }

        return count($this->getMailIdsToFetch($mail_reader, false)) > 0;
    }

    /**
     * @param array $process
     * @return array
     */
    public function doWork(array $process = array())
    {
        return $this->processMailBatch($process, true)['results'];
    }

    /**
     * @param array $process
     * @return crmMailMessage[]
     */
    protected function receiveMailMessages(array $process = array())
    {
        return $this->processMailBatch($process, false)['messages'];
    }

    /**
     * @param crmImap4PluginMailReader $mail_reader
     * @param bool $apply_batch_limit
     * @return array<int|string>
     */
    protected function getMailIdsToFetch(crmImap4PluginMailReader $mail_reader, $apply_batch_limit = true)
    {
        if ($this->shouldUseUidWorkflow()) {
            $last_uid = (int) $this->source->getParam('last_imap_uid', 0);
            $ids = $mail_reader->searchUidsSince($last_uid);
        } else {
            $count = $mail_reader->count();
            $count = (int) $count[0];
            $ids = $count > 0 ? range(1, $count) : array();
        }

        if (!$apply_batch_limit) {
            return $ids;
        }

        $limit = min(count($ids), self::BATCH_LIMIT);
        if (count($ids) > $limit) {
            crmSourceWorker::$not_finished = $this->source->getId();
        }

        return array_slice($ids, 0, $limit);
    }

    /**
     * @param crmImap4PluginMailReader $mail_reader
     * @param int|string $id
     * @return crmMailMessage
     */
    protected function fetchMailMessage(crmImap4PluginMailReader $mail_reader, $id)
    {
        $temp_path = wa('crm')->getTempPath('mail', 'crm');
        $unique_id = uniqid(true);
        $mail_dir = $temp_path.'/'.$unique_id;
        waFiles::create($mail_dir);
        $mail_path = $mail_dir.'/mail.eml';

        $mail_reader->get($id, $mail_path);

        return new crmMailMessage($mail_path);
    }

    /**
     * @param int|string $uid
     */
    protected function advanceLastImapUid($uid)
    {
        $this->source->setParam('last_imap_uid', (string) $uid);
        $this->getSourceParamsModel()->setOne($this->source->getId(), 'last_imap_uid', $uid);
    }

    /**
     * @param crmMailMessage $message
     * @return bool
     */
    protected function shouldDeleteMailFromServer(crmMailMessage $message)
    {
        return !$this->isLeaveMessagesOnServer()
            || crmEmailSourceWorkerStrategy::isSuffixSupportingTestMail($this->source, $message);
    }

    /**
     * @param array $process
     * @param bool $process_messages
     * @return array{messages: crmMailMessage[], results: array}
     */
    protected function processMailBatch(array $process, $process_messages)
    {
        $mail_reader = $this->getMailReader();
        if (!$mail_reader) {
            return array('messages' => array(), 'results' => array());
        }

        $messages = array();
        $results = array();

        foreach ($this->getMailIdsToFetch($mail_reader) as $id) {
            try {
                $message = $this->fetchMailMessage($mail_reader, $id);

                if ($this->shouldDeleteMailFromServer($message)) {
                    $mail_reader->delete($id);
                }

                if ($process_messages) {
                    $results[] = $this->processMailMessage($message);
                    if ($this->shouldUseUidWorkflow()) {
                        $this->advanceLastImapUid($id);
                    }
                } else {
                    $messages[] = $message;
                }
            } catch (Exception $e) {
                if ($process_messages) {
                    $results[] = null;
                }
                $this->logFetchException($e);
            }
        }

        $this->closeMailReader();

        return array('messages' => $messages, 'results' => $results);
    }

    protected function closeMailReader()
    {
        if (!empty($this->cache['mail_reader'])) {
            $this->cache['mail_reader']->close();
        }
        unset($this->cache['mail_reader']);
    }

    protected function logFetchException(Exception $e)
    {
        $msg = join(PHP_EOL, array(
            'Exception',
            $e->getMessage(),
            $e->getTraceAsString(),
        ));
        waLog::log($msg, self::$LOG_FILE);
    }
}
