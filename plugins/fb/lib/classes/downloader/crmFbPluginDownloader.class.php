<?php

class crmFbPluginDownloader
{
    protected $client_contact_id;
    protected $creator_contact_id;
    protected $deal_id;

    public function __construct($client_contact_id, $deal_id, $creator_contact_id)
    {
        $this->client_contact_id = $client_contact_id;
        $this->deal_id = $deal_id;
        $this->creator_contact_id = $creator_contact_id;
    }

    public function downloadFile($file_path, $options = array())
    {
        $protocol = substr($file_path, 0, 5) === 'https' ? 'https' : 'http';
        $context_options = array(
            $protocol => array(
                'method' => 'GET'
            )
        );
        $context = stream_context_create($context_options);
        $input = fopen($file_path, 'rb', false, $context);

        $file_name = pathinfo($file_path, PATHINFO_FILENAME);
        $ext = pathinfo($file_path, PATHINFO_EXTENSION);
        $ext = explode('?', $ext);
        $ext = $ext[0];

        if ($ext) {
            $file_name .= '.'.$ext;
        }

        if (isset($options['file_name'])) {
            $file_name = $options['file_name'];
        }

        $path = wa()->getTempPath('plugins/fb/attachments', 'crm') . '/' . $file_name;
        $output = fopen($path, 'wb');

        stream_copy_to_stream($input, $output);

        fclose($input);
        fclose($output);

        $data = [
            'creator_contact_id' => $this->creator_contact_id,
            'contact_id' => !empty($this->deal_id) ? $this->deal_id * -1 : $this->client_contact_id,
            'ext' => ifset($ext),
            'source_type' => crmFileModel::SOURCE_TYPE_MESSAGE,
        ];

        $file_model = new crmFileModel();
        $id = $file_model->add($data, $path);
        return $id;
    }
}