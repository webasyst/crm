<?php

/**
 * webasyst/crm/?plugin=whatsapp&module=templatesDialog&contact_id=1203720&conversation_id=1203720
 */
 
class crmWhatsappPluginTemplatesDialogAction extends crmBackendViewAction
{
    use crmWhatsappPluginTemplatesTrait;

    protected $contact_id;
    protected $conversation_id;
    protected $source_id;
    
    public function execute()
    {
        list($this->contact_id, $this->source_id, $this->conversation_id) = $this->prepareRequestData();
        $source = new crmWhatsappPluginImSource($this->source_id);
        $api = crmWhatsappPluginApi::factory($source);
        $api_res = $api->getTemplateList();

        if (!empty($api_res['error']['message'])) {
            throw new waException($api_res['error']['message'], 500);
        }
        if (empty($api_res) || empty($api_res['data']) || !is_array($api_res['data'])) {
            throw new waException('Unexpected error', 500);
        }

        $templates = array_map(function ($template) {
            $template['body_hint'] = mb_strlen($template['body']) > 50 ? substr($template['body'], 0, 50) . '...' : $template['body'];
            $body = htmlentities($template['body']);
            $body = preg_replace_callback('/{{(\d+)}}/', function ($matches) use ($template) {
                $idx = $matches[1] - 1;
                return "<span class='state-caution js-whatsapp-body-param-{$matches[1]}' data-body-param-id='{$matches[1]}'>{$template['body_vars'][$idx]}</span>";
            }, $body);
            $template['body'] = nl2br($body);
            if (!empty($template['footer'])) {
                $template['body'] .= '<p class=\'hint\'>' . htmlentities($template['footer']) . '</p>';
            }
            if (!empty($template['header'])) {
                $header = htmlentities($template['header']);
                $header = preg_replace_callback('/{{(\d+)}}/', function ($matches) use ($template) {
                    $idx = $matches[1] - 1;
                    return "<span class='state-caution js-whatsapp-header-param-{$matches[1]}' data-header-param-id='{$matches[1]}'>{$template['header_vars'][$idx]}</span>";
                }, $header);
                $template['body'] = '<p class=\'bold\'>' . $header . '</p>' . $template['body'];
            }
            return $template;
        }, $api_res['data']);

        $this->view->assign([
            'contact_id' => $this->contact_id,
            'conversation_id' => $this->conversation_id,
            'source_id' => $this->source_id,
            'templates' => $templates
        ]);
    }
}