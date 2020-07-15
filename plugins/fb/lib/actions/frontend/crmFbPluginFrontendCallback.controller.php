<?php

class crmFbPluginFrontendCallbackController extends crmJsonController
{
    /**
     * @var crmFbPluginImSource
     */
    protected $source;

    public function execute()
    {
        /** @var crmFbPluginImSource $event */
        $source = $this->getSource();
        if (!$source) {
            $this->sendError('Source not found');
        }

        $event = $this->getEvent();
        try {
            $callback = new crmFbPluginCallback($event, $source);
            $response = $callback->process();
            $this->sendResponse($response);
        } catch (waException $e) {
            $this->sendError($e->getMessage());
        }
    }

    protected function getSource()
    {
        $id = (int)$this->getParameter('id');
        if ($id <= 0) {
            return null;
        }
        $source = crmSource::factory($id);
        if (!($source instanceof crmFbPluginImSource)) {
            return null;
        }
        return $source;
    }

    protected function getSignature()
    {
        return $this->getResponse()->getHeader('X-Facebook-Content-Signature');
    }

    protected function sendResponse($response)
    {
        if ($response !== null) {
            die((string)$response);
        } else {
            die('ok');
        }
    }

    protected function sendError($error)
    {
        $file = 'crm/plugins/fb/callback_event_errors.log';
        waLog::log($error, $file);
        $this->errors = $error;
    }

    protected function getEvent()
    {
        $event = array();
        $event['message'] = json_decode(file_get_contents('php://input'), true);
        $event['subscribe'] = waRequest::request();
        $file = 'crm/plugins/fb/callback_event.log';
        waLog::dump($event, $file);
        return $event ? $event : array();
    }
}