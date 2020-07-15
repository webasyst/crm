<?php

class crmVkPluginCompanySearcher extends crmVkPluginContactSearcher
{
    public function __construct($vk_object, array $options = array())
    {
        $options['is_company'] = true;
        parent::__construct($vk_object, $options);
    }
}
