<?php

class crmTelegramPluginFrontendCallbackController extends crmJsonController
{
    protected $request_body;

    public function execute()
    {
        $source = $this->getSource();
        if (!$source) {
            $this->setError('Source not found');
            return;
        }

        $headers = getallheaders();
        $request_token = ifset($headers['X-Telegram-Bot-Api-Secret-Token']);
        if ($request_token !== $source->getParam('webhook_token')) {
            wa()->getResponse()->setStatus(400);
            $this->setError('Invalid token');
            return;
        }

        $body = $this->readBody();
        if (empty($body)) {
            wa()->getResponse()->setStatus(400);
            $this->setError('Empty body');
            return;
        }

        //waLog::log($body, 'telegram-webhook-debug.log');
        
        $body = json_decode($body, true);
        if (!$body) {
            wa()->getResponse()->setStatus(400);
            $this->setError('Invalid JSON');
            return;
        }

        $worker = crmTelegramPluginImSourceWorker::factory($source);
        if (!$worker) {
            wa()->getResponse()->setStatus(400);
            $this->setError('Worker not found');
            return;
        }

        $new_api_offset = $worker->handleIncomingMessage($body);
        if (!empty($new_api_offset)) {
            $source->saveParam('api_offset', $new_api_offset);
        }
    }

    protected function getSource()
    {
        $id = (int)$this->getParameter('source_id');
        if ($id <= 0) {
            return null;
        }
        $source = crmSource::factory($id);
        if (!($source instanceof crmTelegramPluginImSource)) {
            return null;
        }
        return $source;
    }

    protected function readBody()
    {
        if ($this->request_body === null) {
            $this->request_body = '';
            $contents = file_get_contents('php://input');
            if (is_string($contents) && strlen($contents)) {
                $this->request_body = $contents;
            }
        }
        return $this->request_body;
    }
}