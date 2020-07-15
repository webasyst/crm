<?php

class crmFbPlugin extends crmSourcePlugin
{
    public function factorySource($id, $options = array())
    {
        return new crmFbPluginImSource($id, $options);
    }

    public function backendAssets()
    {
        $version = $this->info['version'];
        $sources = array(
            '<link rel="stylesheet" href="'.wa()->getAppStaticUrl('crm', true).'plugins/fb/css/fb.css?v'.$version.'">',
            '<script src="'.wa()->getAppStaticUrl('crm', true).'plugins/fb/js/fb.js?v'.$version.'"></script>',
        );

        return join("", $sources);
    }


    /**
     * The handler is called when the message is deleted, to delete attachments.
     * @param array $params
     * @throws waException
     */
    public function messageDelete($params)
    {
        $fm = new crmFileModel();
        $mpm = new crmMessageParamsModel();

        $message_ids = (array)ifset($params['ids']);
        $file_ids = array();

        $attachment_params = $mpm->getByField(array('message_id' => $message_ids, 'name' => 'attachments'), 'message_id');
        foreach ($attachment_params as $param) {
            $attachment = json_decode($param['value'], true);
            if (empty($attachment)) {
                continue;
            }
            foreach ($attachment as $type => $ids) {
                foreach ($ids as $id) {
                    $file_ids[] = $id;
                }
            }
        }

        $fm->delete($file_ids);
    }

    public static function sendLog($log)
    {
        if (!waConfig::get('is_template')) {
            $file = 'crm/plugins/fb/callback_event.log';
            waLog::log($log, $file);
        }
    }

    public static function sendError($error)
    {
        if (!waConfig::get('is_template')) {
            $file = 'crm/plugins/fb/callback_event_errors.log';
            waLog::log($error, $file);
        }
    }
}
