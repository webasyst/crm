<?php

/**
 * MAX plugin webhook controller for CRM.
 * Processes incoming updates from MAX messenger.
 */
class crmMaxPluginFrontendCallbackController extends crmJsonController
{
    /**
     * Raw request body
     */
    protected $request_body;

    /**
     * Execute controller
     */
    public function execute()
    {
        $source = $this->getSource();
        if (!$source) {
            $this->setError('Источник не найден');
            return;
        }

        // Verify webhook secret if configured
        $secret = $source->getParam('webhook_secret');
        if (empty($secret)) {
            wa()->getResponse()->setStatus(400);
            $this->setError('Подписка отключена');
            return;
        }

        $headers = getallheaders();
        $request_secret = isset($headers['X-Max-Bot-Api-Secret'])
            ? $headers['X-Max-Bot-Api-Secret']
            : (isset($headers['x-max-bot-api-secret']) ? $headers['x-max-bot-api-secret'] : '');

        if ($request_secret !== $secret) {
            wa()->getResponse()->setStatus(400);
            $this->setError('Неверный секретный ключ');
            return;
        }

        // Read request body
        $body = $this->readBody();
        if (empty($body)) {
            wa()->getResponse()->setStatus(400);
            $this->setError('Пустое тело запроса');
            return;
        }

        // Parse JSON
        $data = json_decode($body, true);
        if (!$data) {
            wa()->getResponse()->setStatus(400);
            $this->setError('Неверный JSON');
            return;
        }

        // Log for debugging (optional)
        waLog::log($body, 'max-webhook-debug.log');

        (new crmMaxPluginImSourceWorker($source))->processUpdate($data);

        // Return success
        $this->response = array('ok' => true);
    }

    /**
     * Get the source (MAX integration)
     *
     * @return crmMaxPluginImSource|null
     */
    protected function getSource()
    {
        $id = (int) $this->getParameter('source_id');
        if ($id <= 0) {
            return null;
        }

        $source = crmSource::factory($id);
        if (!($source instanceof crmMaxPluginImSource)) {
            return null;
        }

        return $source;
    }

    /**
     * Read raw request body
     *
     * @return string
     */
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
