<?php

/**
 * MAX messenger IM source for CRM.
 */
class crmMaxPluginImSource extends crmImSource
{
    protected $provider = 'max';

    public function getProviderName()
    {
        return 'MAX';
    }

    public function getIcon()
    {
        return wa()->getAppStaticUrl('crm/plugins/max/img', true) . 'max.png';
    }

    /**
     * Remove MAX API webhook subscription before deleting the source record.
     */
    public function delete()
    {
        if (!$this->exists()) {
            return;
        }
        $token = $this->getParam('token');
        if (!empty($token)) {
            $api = new crmMaxPluginApi($token, $this->getId());
            $result = $api->removeWebhook($this->getParam('webhook_url'));
            if (is_array($result)) {
                waLog::log('MAX removeWebhook on source delete failed: ' . print_r($result, true), 'crm/max.log');
            }
        }
        parent::delete();
    }

}
