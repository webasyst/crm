<?php

class crmTelegramPlugin extends crmSourcePlugin
{
    public function factorySource($id, $options = array())
    {
        return new crmTelegramPluginImSource($id, $options);
    }

    public function backendAssets()
    {
        $sources = array();
        $sources[] = '<link rel="stylesheet" href="'.wa()->getAppStaticUrl('crm', true).'plugins/telegram/css/telegram.css">';
        $sources[] = '<script src="'.wa()->getAppStaticUrl('crm', true).'plugins/telegram/js/telegram.js"></script>';

        return join("", $sources);
    }

    /**
     * The handler is called when the message is deleted, to delete audio and video files.
     * @param array $params
     */
    public function messageDelete($params)
    {
        $mpm = new crmMessageParamsModel();
        $mam = new crmMessageAttachmentsModel();
        $tfpm = new crmTelegramPluginFileParamsModel();
        $fm = new crmFileModel();

        $message_ids = (array)ifset($params['ids']);
        $file_ids = array_keys($mam->getByField(array('message_id' => $message_ids),'file_id'));

        // Find sticker
        $sticker_ids = $messages_with_stickers = array();
        $message_params = $mpm->get($message_ids);
        foreach ($message_params as $mp) {
            if (isset($mp['sticker_id'])) {
                $sticker_ids[] = $mp['sticker_id'];
            }
        }
        if (!empty($sticker_ids)) {
            $messages_with_stickers = $mpm->getByField(array('name' => 'sticker_id', 'value' => $sticker_ids), true);
        }
        if (count($messages_with_stickers) == 1 && in_array($messages_with_stickers[0]['message_id'], $message_ids)) {
            $tsm = new crmTelegramPluginStickerModel();
            $deleted_sticker = $tsm->getById($messages_with_stickers[0]['value']);
            if ($deleted_sticker) {
                $file_ids[] = $deleted_sticker['id'];
                $tsm->deleteById($deleted_sticker['sticker_id']);
            }
        }

        $tfpm->delete($file_ids);
        $fm->delete($file_ids);
    }
}