<?php

abstract class crmEmailSourceWorker extends crmSourceWorker
{
    /**
     * @var crmEmailSource
     */
    protected $source;

    /**
     * @param crmEmailSource $source
     * @param array $options
     * @return crmEmailSourceWorker|null
     */
    public static function factory(crmEmailSource $source, array $options = array())
    {
        $class_name = get_class($source);
        $class_name .= 'Worker';
        if (!$class_name) {
            return null;
        }
        $object = new $class_name($source, $options);
        if (!($object instanceof self)) {
            return null;
        }
        return $object;
    }

    public static function generateMessageId($deal_id)
    {
        $m = new waMailMessage();
        $message_id = $m->generateId();
        $parts = explode('@', $message_id, 2);
        $parts[0] = substr($parts[0], 0, 16) . '.' . $deal_id . '.' . substr($parts[0], 16);
        return join('@', $parts);
    }

    /**
     * @param crmMailMessage $mail
     * @return array|bool|null
     */
    protected function processMailMessage(crmMailMessage $mail)
    {
        $strategy = crmEmailSourceWorkerStrategy::factory($this->source, $mail);
        if (!$strategy) {
            return null;
        }
        return $strategy->process();
    }

    /**
     * @override
     * @param array $process
     * @return crmMailMessage[]
     */
    protected function receiveMailMessages(array $process = array())
    {
        return array();
    }

    public function isWorkToDo(array $process = array())
    {
        return !empty($process['process_anti_spam']);
    }

    public function doWork(array $process = array())
    {
        $messages = $this->receiveMailMessages($process);
        $results = array();
        foreach ($messages as $index => $message) {
            $res = $this->processMailMessage($message);
            $results[$index] = $res;
        }
        return $results;
    }

    public function doProcessAntiSpam(array $data = array())
    {
        $mail = ifset($data['mail']);
        if (!is_scalar($mail) && !($mail instanceof crmMailMessage)) {
            return null;
        }

        $mail = (string)$mail;
        if (strlen($mail) <= 0) {
            return null;
        }

        $mail = new crmMailMessage($mail, crmMailMessage::CONSTRUCT_TYPE_CONTENT);
        $strategy = crmEmailSourceWorkerStrategy::factory($this->source, $mail);
        if (!$strategy) {
            return null;
        }
        return $strategy->processAntiSpam();
    }

}
