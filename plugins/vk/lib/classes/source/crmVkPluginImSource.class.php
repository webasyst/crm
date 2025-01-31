<?php

class crmVkPluginImSource extends crmImSource
{
    protected $provider = 'vk';

    public function getProviderName()
    {
        return 'VK';
    }

    public function getIcon()
    {
        return wa()->getAppStaticUrl('crm/plugins/vk/img/', true) . 'vk.png';
    }

    public function getGroupId()
    {
        return (int)$this->getParam('group_id');
    }

    public function getAccessToken()
    {
        return $this->getParam('access_token');
    }

    public function getSecretKey()
    {
        return $this->getParam('secret_key');
    }

    public function getAppId()
    {
        return (int)$this->getParam('app_id');
    }

    public function getServiceToken()
    {
        return $this->getParam('service_token');
    }

    public function getApiParams()
    {
        $params = array();
        $param_keys = array(
            'group_id', 'access_token', 'secret_key', 'app_id', 'service_token'
        );
        foreach ($param_keys as $key) {
            $params[$key] = $this->getParam($key);
        }
        return $params;
    }

    public function getFontAwesomeBrandIcon()
    {
        return [
            'icon_fab' => 'vk',
            'icon_color' => '#4C75A3',
        ];
    }

    public function findMessage($vk_message_id)
    {
        return self::getMessageModel()->query("
            SELECT m.* FROM crm_message m 
            INNER JOIN crm_message_params p ON m.id = p.message_id AND p.name = 'id' AND p.value = :id
            WHERE m.source_id = i:source_id", 
            [
                'source_id' => $this->getId(),
                'id' => $vk_message_id,
            ])->fetchAssoc();
    }

    protected function prepareConversationSummaryFromMessage($message)
    {
        $summary = parent::prepareConversationSummaryFromMessage($message);
        $att = ifset($message['params']['attachments'][0], null);
        if (empty($att)) {
            return $summary;
        }
        if (empty($summary) || $summary == '[empty]') {
            $att_type = ifset($att['type']);
            if (!empty($att_type)) {
                return '[file] ' . ifset($att[$att_type]['title'], $att_type);
            }
        } elseif (mb_strpos($summary, '[') !== 0) {
            return '[file] ' . $summary;
        }
        
        return $summary;
    }
}
