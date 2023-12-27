<?php

class crmMessageSendFileMethod extends crmApiAbstractMethod
{
    const FILE_TYPE_IMAGE = 'IMAGE';
    const FILE_TYPE_OTHER = 'OTHER';

    protected $method = self::METHOD_POST;

    public function execute()
    {
        $_json = $this->readBodyAsJson();
        $message_id = (int) ifempty($_json, 'reply_message_id', 0);
        $file = base64_decode(ifempty($_json, 'file', null));
        $file_name = (string) ifempty($_json, 'file_name', '');
        $file_name = trim($file_name);
        $type = (string) ifempty($_json, 'type', self::FILE_TYPE_OTHER);
        $prefix = ($type === self::FILE_TYPE_IMAGE ? 'photos-' : 'files-');

        if (empty($message_id)) {
            throw new waAPIException('empty_reply_message_id', sprintf_wp('Missing required parameter: “%s”.', 'reply_message_id'), 400);
        }
        if (empty($file)) {
            throw new waAPIException('empty_file', sprintf_wp('Missing required parameter: “%s”.', 'file'), 400);
        }
        if (empty($file_name)) {
            throw new waAPIException('empty_file_name', sprintf_wp('Missing required parameter: “%s”.', 'file_name'), 400);
        }
        if (!in_array($type, [self::FILE_TYPE_IMAGE, self::FILE_TYPE_OTHER])) {
            throw new waAPIException('invalid_file_type', _w('Invalid file type.'), 400);
        }
        if (
            in_array(trim($file_name), ['.', '..'])
            || !preg_match('#^[^:*?"<>|/\\\\]+$#', $file_name)
        ) {
            throw new waAPIException('invalid_file_name', _w('Invalid file name.'), 400);
        }

        $message = $this->getMessageModel()->getById($message_id);
        if (!$message) {
            throw new waAPIException('invalid_reply_message_id', _w('Message not found'), 400);
        }
        if (!$this->getCrmRights()->canViewMessage($message)) {
            throw new waAPIException('access_denied', _w('Access denied'), 403);
        }
        if ($message['transport'] !== crmMessageModel::TRANSPORT_IM) {
            throw new waAPIException('invalid_reply_message_id', _w('Message was not sent via messenger.'), 400);
        }
        if ($message['source_id'] < 1) {
            throw new waAPIException('invalid_reply_message_id', _w('Message source not found.'), 400);
        }

        $name = md5(uniqid(__METHOD__));
        $data = [
            'body' => ' ',
            'hash' => $name
        ];

        $temp_path = wa('crm')->getTempPath('mail', 'crm').'/'.$prefix.$name;
        waFiles::create($temp_path, true);
        $n = file_put_contents($temp_path."/$file_name", $file);
        if (!$n) {
            throw new waAPIException('server_error', _w('File could not be saved.'), 500);
        }

        $source = crmSource::factory($message['source_id']);
        $result = crmSourceMessageSender::replyToMessage($source, $message, $data);

        if ($result['status'] === 'ok') {
            $this->response = ['message_id' => (int) ifset($result, 'response', 'message_id', 0)];
        } else {
            $error = implode(' ', (array) ifempty($result, 'errors', [_w('Unknown error')]));
            throw new waAPIException('server_error', $error, 500);
        }
    }
}
