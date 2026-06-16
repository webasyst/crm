<?php

class crmImap4Plugin extends crmSourcePlugin
{
    public function factorySource($id, $options = array())
    {
        return new crmImap4PluginEmailSource($id, $options);
    }
}
