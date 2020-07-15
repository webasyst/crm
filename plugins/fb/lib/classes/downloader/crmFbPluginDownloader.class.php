<?php

class crmFbPluginDownloader
{
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

        $path = wa()->getTempPath('plugins/fb/attachments/'.$file_name, 'crm');

        waFiles::create($path);
        $output = fopen($path, 'wb');

        stream_copy_to_stream($input, $output);

        fclose($input);
        fclose($output);

        $data = array(
            'ext' => ifset($ext),
        );

        $file_model = new crmFileModel();
        $id = $file_model->add($data, $path);
        return $id;
    }
}