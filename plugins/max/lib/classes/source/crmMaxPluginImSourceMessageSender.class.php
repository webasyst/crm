<?php

/**
 * MAX messenger message sender for CRM.
 */
class crmMaxPluginImSourceMessageSender extends crmImSourceMessageSender
{
    /**
     * @var crmMaxPluginApi
     */
    protected $api;

    /**
     * @var crmMaxPluginImSource
     */
    protected $source;

    protected $file_model;

    protected $is_auto_response = false;

    protected $is_contact_updated = false;

    protected $do_not_save_this_message = false;

    protected $do_not_save_attachments = false;

    /**
     * Constructor
     *
     * @param crmMaxPluginImSource $source
     */
    public function __construct(crmMaxPluginImSource $source, $message, array $options = [])
    {
        parent::__construct($source, $message, $options);
        $this->api = new crmMaxPluginApi($this->source->getParam('token'), $this->source->getId());
        $this->do_not_save_attachments = $this->source->getParam('do_not_save_attachments');
    }

    public function reply($data)
    {
        // Check uploaded photos and files
        $uploaded_photos = $this->getUploadedPhotos(ifset($data['hash']));
        $uploaded_files = $this->getUploadedFiles(ifset($data['hash']));
        $uploaded_files = array_merge($uploaded_photos, $uploaded_files);

        $errors = $this->validate($data, !empty($uploaded_files));
        if ($errors) {
            return $this->fail($errors);
        }
        $this->is_auto_response = !empty($data['is_auto_response']);
        $this->is_contact_updated = !empty($data['is_contact_updated']);
        $this->do_not_save_this_message = !empty($data['do_not_save_this_message']);

        $max_user_id = ($this->message['direction'] == crmMessageModel::DIRECTION_IN) ? $this->message['from'] : $this->message['to'];
        $max_chat_id = ifset($this->message['params']['max_chat_id']);

        if (empty($max_user_id)) {
            $this->logError('Unknown MAX user ID.');
            return $this->fail(['common' => _wd('crm_max', 'Unknown MAX user ID.')]);
        }

        if (ifset($data, 'verify_link', 'url', false)) {
            $data['body'] = ifset($data['verify_body'], _w('Please verify your client profile.'));
            $data['inline_keyboard'] = [[
                [
                    'type' => 'link',
                    'text' => ifset($data['verify_link']['text'], _w('Verify client profile')),
                    'url' => $data['verify_link']['url'],
                ]
            ]];
        }

        $sanitizer = new crmMaxPluginMessageBodyWorker();
        $text = ifset($data['is_plain_text']) ? $sanitizer->handleMarkUp($data['body']) : $sanitizer->sanitize($data['body']);
//        $text = ifset($data['is_plain_text']) ? $data['body'] : $sanitizer->sanitize($data['body']);
        $params = [
//            'format' => $data['is_plain_text'] ? 'markdown' : 'html',
            'format' => 'html',
        ];
        if (ifset($data['inline_keyboard'])) {
            $params['inline_keyboard'] = $data['inline_keyboard'];
        }

        $attachments = [];
        $file_ids = [];
        $images_count = 0;
        $non_images_count = 0;
        if (!empty($uploaded_files)) {
            foreach ($uploaded_files as $file_path) {
                $is_empty_file = (int)@filesize($file_path) == 0;
                if (!$is_empty_file) {
                    $action = $this->api->getActionByFilePath($file_path);
                    $this->api->sendChatAction($max_chat_id, $action);
                    $attachment = $this->api->uploadFile($file_path);
                }
                
                if (empty($attachment)) {
                    foreach ($uploaded_files as $file_to_delete) {
                        waFiles::delete($file_to_delete);
                    }
                    $error = $is_empty_file ? ['message' => _wd('crm_max', 'File is empty.')] : $this->api->getLastError();
                    $this->logError('Failed to upload file: ' . ifset($error['message']));
                    return $this->fail(['common' => ifset($error['message'], _wd('crm_max', 'Failed to upload the file.'))]);
                }
                $attachments[] = $attachment;
                if ($attachment['type'] === 'image') {
                    $images_count++;
                } else {
                    $non_images_count++;
                }
            }
            foreach ($uploaded_files as $file_path) {
                $file_ids[] = $this->saveFile($file_path);
            }
        }

        $new_message = null;
        if ($non_images_count > 1 || ($non_images_count > 0 && $images_count > 0)) {
            // send every attach separately
            $sent_attachments = [];
            $message_text = $text;
            foreach ($attachments as $attachment) {
                $new_message = $this->sendMessage($max_user_id, $text, [ $attachment ], $params);
                if (empty($new_message['message'])) {
                    return $this->handleSendMessageError($text, $max_user_id);
                }
                if (!empty($new_message['message']['body']['attachments'])) {
                    $sent_attachments[] = $new_message['message']['body']['attachments'][0];
                }
                $text = '';
            }
            $new_message['message']['body']['text'] = $message_text;
            $new_message['message']['body']['attachments'] = $sent_attachments;
            $attachments = [];
        }
        if (!empty($text) || !empty($attachments)) {
            $new_message = $this->sendMessage($max_user_id, $text, $attachments, $params);
            if (empty($new_message['message'])) {
                return $this->handleSendMessageError($text, $max_user_id);
            }
        }

        $new_message = $new_message['message'];
        if (empty($new_message['body']['mid'])) {
            return $this->fail(['common' => 'sendMessage result value is wrong']);
        }
        $max_message_id = $new_message['body']['mid'];

        if ($this->do_not_save_this_message) {
            return $this->ok(['max_message_id' => $max_message_id]);
        }

        if (!empty($new_message['body']['attachments'])) {
            // remove video & audio attachments - it saved on server in any case - do_not_save_attachments does not matter
            $new_message['body']['attachments'] = array_filter($new_message['body']['attachments'], function ($attachment) {
                return !in_array($attachment['type'], ['video', 'audio']);
            });
        }

        $message_id = $this->createMessage($new_message, $file_ids);

        return $this->ok([
            'max_message_id' => $max_message_id,
            'message_id'     => $message_id,
        ]);
    }

    protected function handleSendMessageError($text, $max_user_id)
    {
        $error = $this->api->getLastError();
        if (ifset($error['http_code']) === 403) {
            $error_message = strpos(ifset($error['message']), 'Key: error.dialog.suspended,') === 0 ?
                _wd('crm_max', 'Blocked by the client.') :
                ifset($error['message'], _wd('crm_max', 'Blocked by the client.'));
            $this->createInternalServiceMessage($max_user_id, $error_message);
        } elseif (ifset($error['http_code']) === 400 && $this->is_auto_response) {
            $this->createInternalServiceMessage($max_user_id,
                _wd('crm_max', 'Failed to send the message:') . '<blockquote>' . htmlspecialchars($text) . '</blockquote>' .
                _wd('crm_max', 'Message refused by MAX:') . '<br><i>' .
                htmlspecialchars(ifset($error['message'], _wd('crm_max', 'Incorrect request to MAX API.'))) . '</i>'
            );
        }

        return $this->fail([ 'common' => htmlspecialchars(ifset($error['message'], _w('Unknown error.'))) ]);
    }

    protected function sendMessage($max_user_id, $text, $attachments, $params, $attempt = 1)
    {
        $new_message = $this->api->sendMessage($max_user_id, $text, $attachments, $params);
        if ($new_message === false && !empty($attachments) && $attempt <= 5) {
            $error = $this->api->getLastError();
            if (!empty($error['code']) && $error['code'] === 'attachment.not.ready') {
                waLog::dump('Attachment not ready. Attempt: ' . $attempt, 'crm/max.log');
                sleep(2**$attempt);
                return $this->sendMessage($max_user_id, $text, $attachments, $params, $attempt + 1);
            }
        }

        return $new_message;
    }

    protected function prepareMessage($message, $attachments_file_ids = [])
    {
        $message_params = [
            'max_message_id' => $message['body']['mid'],
        ];
        if ($this->is_contact_updated) {
            $message_params['is_contact_updated'] = 1;
        }

        if (!empty($message['body']['attachments']) && $this->do_not_save_attachments) {
            $message_params['max_attachments'] = array_map( function ($file) {
                $res = [
                    'type' => $file['type'],
                    'url'  => $file['payload']['url'],
                ];
                if (isset($file['filename'])) {
                    $res['filename'] = $file['filename'];
                }
                if (isset($file['size'])) {
                    $res['size'] = $file['size'];
                }
                return $res;
            }, $message['body']['attachments']);
        }

        $creator_contact_id = $this->is_auto_response ? 0 : wa()->getUser()->getId();
        $data = [
            'creator_contact_id' => $creator_contact_id,
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'contact_id'         => $this->message['contact_id'],
            'deal_id'            => ifset($this->message['deal_id']),
            'subject'            => '',
            'body'               => crmMaxPluginMessageBodyWorker::parser($message['body']),
            'from'               => $this->is_auto_response ? _wd('crm_max', 'Bot’s auto-reply') : $message['sender']['user_id'],
            'to'                 => $message['recipient']['user_id'],
            'params'             => $message_params,
        ];

        if (!empty($attachments_file_ids)) {
            $data['attachments'] = $attachments_file_ids;
        }

        return $data;
    }

    protected function createMessage($message, $attachments_file_ids = [])
    {
        $data = $this->prepareMessage($message, $attachments_file_ids);
        $message_id = $this->source->createMessage($data, crmMessageModel::DIRECTION_OUT);
        return $message_id;
    }

    protected function validate($data, $is_attachments)
    {
        $body = (string)ifset($data['body']);
        if (!$is_attachments && strlen($body) <= 0) {
            return array(
                'body' => _wp('This is a required field.')
            );
        }
        return array();
    }

    protected function getUploadedPhotos($hash)
    {
        $file_paths = [];
        $temp_path = wa('crm')->getTempPath('mail', 'crm');
        $mail_dir = $temp_path.'/'.'photos-'.$hash;
        foreach (waFiles::listdir($mail_dir) as $file_path) {
            $full_file_path = $mail_dir.'/'.$file_path;
            $file_paths[] = $full_file_path;
        }
        return $file_paths;
    }

    protected function getUploadedFiles($hash)
    {
        $file_paths = [];
        $temp_path = wa('crm')->getTempPath('mail', 'crm');
        $mail_dir = $temp_path.'/'.'files-'.$hash;
        foreach (waFiles::listdir($mail_dir) as $file_path) {
            $full_file_path = $mail_dir.'/'.$file_path;
            $file_paths[] = $full_file_path;
        }
        return $file_paths;
    }

    protected function fail($errors)
    {
        return array(
            'status' => 'fail',
            'errors' => $errors
        );
    }

    protected function ok($response)
    {
        return array(
            'status'   => 'ok',
            'response' => $response
        );
    }

    private function logError($error)
    {
        waLog::log($error, 'crm/max.log');
    }

    protected function saveFile($file_path)
    {
        if (empty($file_path)) {
            return;
        }

        $ext = pathinfo($file_path, PATHINFO_EXTENSION);
        $type = $this->api->getFileTypeByExt(strtolower($ext));
        if ($this->do_not_save_attachments && !in_array($type, ['video', 'audio'])) {
            // video & audio files will be saved in any case
            waFiles::delete($file_path);
            return;
        }

        $filename = pathinfo($file_path, PATHINFO_BASENAME);
        $data = [
            'creator_contact_id' => wa()->getUser()->getId(),
            'name' => $filename,
            'ext' => $ext,
            'source_type' => crmFileModel::SOURCE_TYPE_MESSAGE,
        ];
        if (!empty($this->message['deal_id'])) {
            $data['contact_id'] = -1 * $this->message['deal_id'];
        } else {
            $data['contact_id'] = $this->message['contact_id'];
        }

        $file_id = $this->getFileModel()->add($data, $file_path);
        waFiles::delete($file_path);
        return $file_id;
    }

    protected function createInternalServiceMessage($max_user_id, $text)
    {
        $message_id = $this->source->createMessage([
            'creator_contact_id' => 0,
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'contact_id'         => $this->message['contact_id'],
            'deal_id'            => ifset($this->message['deal_id']),
            'subject'            => '',
            'body'               => $text,
            'from'               => _wd('crm_max', 'Internal service message'),
            'to'                 => $max_user_id,
            'params'             => ['internal' => '1'],
        ], crmMessageModel::DIRECTION_OUT);
        return $message_id;
    }

    /**
     * Get API instance
     *
     * @return crmMaxPluginApi
     */
    public function getApi()
    {
        return $this->api;
    }

    protected function getFileModel()
    {
        if (empty($this->file_model)) {
            $this->file_model = new crmFileModel();
        }
        return $this->file_model;
    }
}
