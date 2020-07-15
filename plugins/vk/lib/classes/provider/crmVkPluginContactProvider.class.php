<?php

abstract class crmVkPluginContactProvider
{
    public static function factory(crmVkPluginVkContact $vk_contact)
    {
        if ($vk_contact instanceof crmVkPluginVkUser) {
            return new crmVkPluginPersonProvider($vk_contact);
        }
        if ($vk_contact instanceof crmVkPluginVkGroup) {
            return new crmVkPluginCompanyProvider($vk_contact);
        }
        throw new crmVkPluginException("Invalid argument");
    }

    /**
     * @return crmContact
     */
    abstract public function provide();
}
