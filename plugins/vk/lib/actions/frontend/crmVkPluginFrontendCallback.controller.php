<?php

class crmVkPluginFrontendCallbackController extends crmJsonController
{
    /**
     * @var crmVkPluginImSource
     */
    protected $source;

    public function execute()
    {
        $source = $this->getSource();
        if (!$source) {
            $this->sendError('Source not found');
        }

        $event = $this->getEvent();
        waLog::dump($event, 'vk-callback-debug.log');
        try {
            $callback = new crmVkPluginCallback($event, $source);
            $response = $callback->process();
            $this->sendOk($response);
        } catch (waException $e) {
            //$this->sendError($e->getMessage());
            waLog::log($e->getMessage(), 'crm/plugins/vk/callback_event_errors.log');
            $this->sendOk();
        }
    }

    protected function getSource()
    {
        $id = (int)$this->getParameter('id');
        if ($id <= 0) {
            return null;
        }
        $source = crmSource::factory($id);
        if (!($source instanceof crmVkPluginImSource)) {
            return null;
        }
        return $source;
    }

    protected function sendError($error, $status = 404)
    {
        $this->getResponse()->setStatus($status);
        $file = 'crm/plugins/vk/callback_event_errors.log';
        waLog::log($error, $file);
        die((string)$error);
    }

    protected function sendOk($message = null)
    {
        if ($message !== null) {
            die((string)$message);
        } else {
            die('ok');
        }
    }

    protected function getEvent()
    {
        $contents = $this->getInputContent();
        $event = json_decode($contents, true);
        return $event ? $event : array();
    }

    /**
     * @see http://php.net/manual/en/function.file-get-contents.php#85008
     * @return string
     */
    protected function getInputContent()
    {
        return file_get_contents('php://input');
    }
}
