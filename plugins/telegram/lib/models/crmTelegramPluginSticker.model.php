<?php

class crmTelegramPluginStickerModel extends crmModel
{
    protected $table = 'crm_telegram_plugin_sticker';

    public function getByTelegramFileId($file_id) {
        $fm = $this->getFileModel();
        $sql = "SELECT s.id AS sticker_id, f.*
                FROM {$fm->getTableName()} AS f
                  JOIN {$this->getTableName()} AS s
                    ON s.telegram_file_id = ?
                WHERE f.id = s.crm_file_id
                LIMIT 1";
        $sticker = $this->query($sql, $file_id)->fetchAssoc('id');
        return $sticker;
    }

    public function getById($id)
    {
        $fm = $this->getFileModel();
        $sql = "SELECT s.id AS sticker_id, f.*
                FROM {$fm->getTableName()} AS f
                  JOIN {$this->getTableName()} AS s
                    ON s.id = ?
                WHERE f.id = s.crm_file_id
                LIMIT 1";
        $sticker = $this->query($sql, $id)->fetchAssoc('id');
        return $sticker;
    }
}