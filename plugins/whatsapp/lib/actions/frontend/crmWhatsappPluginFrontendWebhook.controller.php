<?php

class crmWhatsappPluginFrontendWebhookController extends crmJsonController
{
    protected $request_body;
    protected $source;

    public function execute()
    {
        $this->source = $this->getSource();
        if (!$this->source) {
            $this->sendError('Source not found', 404);
        }

        if (waRequest::method() === waRequest::METHOD_GET) {
			$this->doGet();
		} elseif (waRequest::method() === waRequest::METHOD_POST) {
			$this->doPost();
		}
    }

    protected function doGet()
    {
        $token = waRequest::get('hub_verify_token', null, waRequest::TYPE_STRING_TRIM);
        $mode = waRequest::get('hub_mode', null, waRequest::TYPE_STRING_TRIM);
        $challenge = waRequest::get('hub_challenge', '', waRequest::TYPE_STRING_TRIM);

        if (empty($mode) || empty($token)) {
            $this->sendError('Empty mode or token', 400);
        }

        if ($mode !== 'subscribe') {
            $this->sendError('Invalid mode', 400);
        }

        if ($token !== $this->source->getParam('webhook_token')) {
            $this->sendError('Invalid token', 403);
        }

        die($challenge);
    }

    protected function doPost()
    {
        $headers = getallheaders();
        $request_sign = ifset($headers['X-Hub-Signature-256']);
        if ($request_sign !== 'sha256=' . hash_hmac('sha256', $this->readBody(), $this->source->getParam('app_secret'))) {
            $this->sendError('Invalid signature', 403);
        }

        $data = waUtils::jsonDecode($this->readBody(), true);
        if (empty($data)) {
            $this->sendError('Invalid JSON', 400);
        }

        //waLog::dump($data, 'whatsapp-webhook-debug.log');

        $whatsapp_contacts = [];
        $whatsapp_messages = [];
        $message_statuses = [];
        foreach (ifset($data['entry'], []) as $entry) {
            foreach (ifset($entry['changes'], []) as $change) {
                if (ifset($change['value']['metadata']['phone_number_id']) === $this->source->getParam('phone_id')) {
                    $whatsapp_contacts += ifset($change['value']['contacts'], []);
                    $whatsapp_messages += ifset($change['value']['messages'], []);
                    $message_statuses += ifset($change['value']['statuses'], []);
                }
            }
        }
        $whatsapp_contacts = array_reduce($whatsapp_contacts, function ($carry, $item) {
            $carry[(string)$item['wa_id']] = $item['profile']['name'];
            return $carry;
        });
        foreach ($whatsapp_messages as $m) {
            $m['from_name'] = ifset($whatsapp_contacts[$m['from']], $m['from']);
            $this->saveMessage($m);
        }
        foreach ($message_statuses as $status) {
            $this->saveStatus($status);
        }
    }

    protected function sendError($error, $status = 500)
    {
        $this->getResponse()->setStatus($status);
        $this->getResponse()->sendHeaders();
        die($error);
    }

    protected function getSource()
    {
        $id = (int)$this->getParameter('source_id');
        if ($id <= 0) {
            return null;
        }
        $source = crmSource::factory($id);
        if (!($source instanceof crmWhatsappPluginImSource)) {
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

    protected function getContact($phone, $name)
    {
        $is_new_contact = false;
        $items = (new waContactDataModel)->getByField([
            'field' => ['phone', 'im'], 
            'value' => $phone,
        ], true);
        
        $items = array_filter($items, function ($item) {
            return in_array($item['field'], ['phone', 'im']) && $item['ext'] === 'whatsapp' || $item['field'] === 'phone' && $item['sort'] == 0;
        }) ?: $items;

        // Try to find contact by whatsapp id (sort found items by field - im before, phone after)
        array_multisort(array_column($items, 'field'), $items);

        $contact_ids = array_column($items, 'contact_id');
        $contact = null;
        foreach ($contact_ids as $contact_id) {
            $contact = new crmContact($contact_id);
            if ($contact->exists()) {
                break;
            }
        }

        if (empty($contact) || !$contact->exists()) {
            $is_new_contact = true;
            $contact = new crmContact();
            $contact->set('firstname', $name);
            $contact->add('phone.whatsapp', $phone);
            $contact->add('im.whatsapp', $phone);
            $contact->save();
        } else {
            $whatsapp_items = array_filter($items, function ($item) use ($contact) {
                return $item['contact_id'] == $contact->getId() && $item['field'] === 'im' && $item['ext'] === 'whatsapp';
            });
            if (empty($whatsapp_items)) {
                $contact->add('im.whatsapp', $phone);
                $contact->save();
            }
        }
        
        return [$contact, $is_new_contact];
    }

    protected function saveMessage($message)
    {
        $data = $this->prepareMessage($message);
        $message_id = $this->source->createMessage($data);
        return $message_id;
    }

    protected function prepareMessage($message)
    {
        list($contact, $is_new_contact) = $this->getContact($message['from'], $message['from_name']);
        // Ignore blocked users
        if ($contact['is_user'] == -1) {
            return;
        }

        if ($is_new_contact) {
            // add contacts to segments
            $this->source->addContactsToSegments($contact->getId());

            // set local
            $locale = $this->source->getParam('locale');
            if ($locale) {
                $contact->save(['locale' => $locale]);
            }
        }

        $deal_id = $this->findDeal($message, $contact);
        $data = $this->prepareMessageData($message, $contact, $deal_id);
        return $data;
    }

    protected function findDeal($message, crmContact $contact)
    {
        // Find opened conversation by this source and this contact
        $conversation = $this->source->findConversation($contact->getId());
        if ($conversation) {
            return $conversation['deal_id'];
        }

        // If conversation not found it would be created in createMessage step and by that time we need find deal for this new message

        $dm = new crmDealModel();
        $deals = $dm->getByField(array(
            'contact_id' => $contact->getId(),
            'status_id'  => crmDealModel::STATUS_OPEN,
            'funnel_id'  => $this->source->getFunnelId(),
        ), true);

        if (count($deals) > 1 && $this->source->getParam('create_deal')) {
            return $this->createDeal($message, $contact);
        } elseif (!empty($deals)) {
            return $deals[0]['id'];
        } elseif ($this->source->getParam('create_deal')) {
            return $this->createDeal($message, $contact);
        }

        return null;
    }

    protected function createDeal($message, crmContact $contact)
    {
        $description = $message['text']['body'];
        $deal = array(
            'name'               => $contact->getName(),
            'contact_id'         => $contact->getId(),
            'creator_contact_id' => $contact->getId(),
            'description'        => $description ? $description : null,
        );
        return $this->source->createDeal($deal);
    }

    protected function prepareMessageData($message, crmContact $contact, $deal_id)
    {
        $body = ifset($message['text']['body'], '');
        $attachements = [];
        $message_params = [
            'whatsapp_message_id'     => $message['id'],
            'whatsapp_contact_phone'  => $message['from'],
        ];
        if ($message['type'] === 'location' && ifset($message['location']['latitude'], false) && ifset($message['location']['longitude'], false)) {
            $message_params['location'] = $message['location']['latitude'] . ', ' . $message['location']['longitude'];
            if (ifset($message['location']['name'], false)) {
                $message_params['location_title'] = $message['location']['name'];
            }
            if (ifset($message['location']['url'], false)) {
                $message_params['location_url'] = $message['location']['url'];
            }
        }

        if ($message['type'] !== 'text' && ifset($message, $message['type'], 'id', false)) {
            $caption = ifset($message[$message['type']]['caption'], '');
            if (!empty($caption) || $caption === '0') {
                $message_params['caption'] = $caption;
            }

            $attachement = $message[$message['type']];
            $downloader = crmWhatsappPluginDownloader::factory($this->source);
            $file_path = $downloader->downloadMedia($attachement['id'], ifset($attachement['filename']));
            
            $ext = pathinfo($file_path, PATHINFO_EXTENSION);
            $crm_file_id = (new crmFileModel)->add([
                'creator_contact_id' => $contact->getId(),
                'contact_id'         => !empty($deal_id) ? $deal_id * -1 : $contact->getId(),
                'ext' => $ext,
                'source_type' => crmFileModel::SOURCE_TYPE_MESSAGE,
            ], $file_path);
            $attachements = [$crm_file_id];

            try {
                waFiles::delete($file_path);
            } catch (Exception $e) {
            }
        } elseif ($message['type'] === 'unsupported') {
            $errors = ifset($message, 'errors', []);
            $message_params['error_code'] = 'unsupported';
            $message_params['error_details'] = join("\n", array_merge(array_column($errors, 'message'), array_column(array_column($errors, 'error_data'), 'details')));
        }
        
        $data = [
            'creator_contact_id' => $contact->getId(),
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'contact_id'         => $contact->getId(),
            'deal_id'            => $deal_id,
            'subject'            => '',
            'body'               => $body,
            'from'               => $contact->getName(),
            'to'                 => $this->source->getId(),
            'params'             => $message_params,
        ];

        if (!empty($attachements)) {
            $data['attachments'] = $attachements;
        }
        return $data;
    }

    protected function saveStatus($status)
    {
        $message_params_model = new crmMessageParamsModel();
        $message_whatsapp_id_param_record = $message_params_model->getByField([
            'name' => 'whatsapp_message_id',
            'value' => $status['id'],
        ]);
        if (empty($message_whatsapp_id_param_record)) {
            return;
        }
        $normalized_status = $this->normalizeMessageStatus($status['status']);
        if (empty($normalized_status)) {
            return;
        }
        $message_id = $message_whatsapp_id_param_record['message_id'];
        $errors = array_column(array_column(ifempty($status['errors'], []), 'error_data'), 'details');
        $errors = array_map(function ($err) {
            return _wd('crm_whatsapp', $err);
        }, $errors);
        $error = join("\n", $errors);
        $this->source->handleMessageStatus($message_id, $normalized_status, $error);
    }

    protected function normalizeMessageStatus($status)
    {
        $statuses = [
            'sent' => crmImSource::STATUS_SENT,
            'delivered' => crmImSource::STATUS_DELIVERED,
            'read' => crmImSource::STATUS_READ,
            'failed' => crmImSource::STATUS_FAILED,
        ];
        return ifset($statuses[$status], null);
    }
}