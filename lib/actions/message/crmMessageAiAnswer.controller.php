<?php

class crmMessageAiAnswerController extends waJsonController
{
    public function execute()
    {
        $message_text = waRequest::post('message_text', '', waRequest::TYPE_STRING_TRIM);
        $domain = waRequest::post('domain', '', waRequest::TYPE_STRING_TRIM);

        // If not specified in POST, use any non-redirect domain from global routing or current backend domain.
        if (!$domain) {
            foreach (wa()->getRouting()->getDomains() as $d) {
                if (!wa()->getRouting()->isAlias($d)) {
                    $domain = $d;
                    break;
                }
            }
            if (!$domain) {
                $domain = wa()->getRouting()->getDomain();
            }
        }

        try {
            $search = new crmServicesSearch([
                'domain' => $domain,
                'improve_query' => false,
                'text_format' => 'plaintext_with_links', // plaintext_with_links|plaintext|html|markdown
            ]);
            $this->response = [
                'answer' => $search->getAiResponse($message_text),
            ];
        } catch (Throwable $e) {
            $this->errors = [$e->getMessage()];
        }
    }
}
