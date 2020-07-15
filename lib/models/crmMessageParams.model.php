<?php

class crmMessageParamsModel extends crmParamsModel
{
    protected $table = 'crm_message_params';
    protected $external_id = 'message_id';
    protected $serializing = true;

    public static function isColumnMb4($column)
    {
        return crmModel::isTableColumnMb4('crm_message_params', $column);
    }


    /**
     * @param int|array $message_ids
     * @return array|mixed
     * @throws waException
     */
    public function getParamsByMessage($message_ids)
    {
        // Sanitize and validate
        $message_ids = array_filter(array_map('intval', (array)$message_ids));
        $message_ids = array_keys(array_flip($message_ids));
        if (!$message_ids) {
            return array();
        }

        return $this->get($message_ids);
    }
}
