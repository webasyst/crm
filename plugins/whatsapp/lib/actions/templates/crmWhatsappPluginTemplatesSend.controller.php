<?php

class crmWhatsappPluginTemplatesSendController extends crmJsonController
{
    use crmWhatsappPluginTemplatesTrait;

    public function execute()
    {
        list($contact_id, $source_id, $conversation_id) = $this->prepareRequestData();

        $template_body_params = waRequest::post('body_params', [], waRequest::TYPE_ARRAY);
        $template_header_params = waRequest::post('header_params', [], waRequest::TYPE_ARRAY);
        $template_name = waRequest::post('template_name', null, waRequest::TYPE_STRING_TRIM);
        $template_lang = waRequest::post('template_lang', null, waRequest::TYPE_STRING_TRIM);

        if (empty($template_name) || empty($template_lang)) {
            //$this->getResponse()->setStatus(400);
            $this->errors = [
                'error_code' => 'missing_required_param',
                'error_description' => _w('Missing required param'),
            ];
            return;
        }

        $to = null;
        $deal_id = null;
        if (!empty($conversation_id)) {
            $conversation = (new crmConversationModel)->getById($conversation_id);
            if (!empty($conversation['last_message_id'])) {
                $whatsapp_phone = (new crmMessageParamsModel)->getByField([
                    'message_id' => $conversation['last_message_id'],
                    'name' => 'whatsapp_contact_phone',
                ]);
                if (!empty($whatsapp_phone)) {
                    $to = $whatsapp_phone['value'];
                }
            }
            $deal_id = ifset($conversation['deal_id']);
        }

        $contact = new crmContact($contact_id);
        if (empty($to) && !empty($contact_id)) {
            $to = $contact->get('im.whatsapp', 'default') ?: $contact->get('phone', 'default');
        }

        if (empty($to)) {
            //$this->getResponse()->setStatus(400);
            $this->errors = [
                'error_code' => 'invalid_recipient',
                'error_description' => _w('No recipient WhatsApp account'),
            ];
            return;
        }

        $source = new crmWhatsappPluginImSource($source_id);
        $api = crmWhatsappPluginApi::factory($source);

        $template = $api->getTemplate($template_name, $template_lang);

        $header = ifset($template['header'], '');
        foreach ($template_header_params as $idx => $param) {
            $header = str_replace('{{'.$idx.'}}', $param, $header);
        }
        if (preg_match('/{{\d+}}/', $header)) {
            //$this->getResponse()->setStatus(400);
            $this->errors = [
                'error_code' => 'not_enougth_vars',
                'error_description' => _w('Not all header variables set'),
            ];
            return;
        }

        $body = $template['body'];
        foreach ($template_body_params as $idx => $param) {
            $body = str_replace('{{'.$idx.'}}', $param, $body);
        }
        if (preg_match('/{{\d+}}/', $body)) {
            //$this->getResponse()->setStatus(400);
            $this->errors = [
                'error_code' => 'not_enougth_vars',
                'error_description' => _w('Not all body variables set'),
            ];
            return;
        }

        $template_params = ['body_params' => $template_body_params, 'header_params' => $template_header_params];
        if (!empty($template['header_image_example_url'])) {
            $template_params['header_image_url'] = $template['header_image_example_url'];
        }

        $api_res = $api->sendTemplateMessage($to, $template_name, $template_lang, $template_params);

        if (!empty($api_res['error']['message'])) {
            //$this->getResponse()->setStatus(500);
            $this->errors = [
                'error_code' => 'sending_fail',
                'error_description' => $api_res['error']['message'],
            ];
            return;
        }
        if (empty($api_res)) {
            //$this->getResponse()->setStatus(500);
            $this->errors = [
                'error_code' => 'sending_fail',
                'error_description' => _w('Unexpected error'),
            ];
            return;
        }

        $whatsapp_message_data = [
            'whatsapp_contact_phone' => ifset($api_res, 'contacts', 0, 'input', ''),
            'whatsapp_contact_id' => ifset($api_res, 'contacts', 0, 'wa_id', ''),
            'whatsapp_message_id' => ifset($api_res, 'messages', 0, 'id', ''),
        ];

        if (empty($contact->get('im.whatsapp', 'default')) && !empty($whatsapp_message_data['whatsapp_contact_id'])) {
            $contact->set('im.whatsapp', $whatsapp_message_data['whatsapp_contact_id'], 'default');
            $contact->save();
        }

        if (!empty($template['footer'])) {
            $whatsapp_message_data['message_footer'] = $template['footer'];
        }
        if (!empty($header)) {
            $whatsapp_message_data['message_header'] = $header;
        }

        $data = [
            'creator_contact_id' => wa()->getUser()->getId(),
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'contact_id'         => $contact_id,
            'deal_id'            => $deal_id,
            'subject'            => '',
            'body'               => $body,
            'from'               => $source_id,
            'to'                 => $contact->getName(),
            'params'             => $whatsapp_message_data,
        ];
        $message_id = $source->createMessage($data, crmMessageModel::DIRECTION_OUT);

        $this->response = ['message_id' => $message_id];
    }

}