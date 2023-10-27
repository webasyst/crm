<?php

class crmImapPlugin extends crmSourcePlugin
{
    public function factorySource($id, $options = array())
    {
        return new crmImapPluginEmailSource($id, $options);
    }
}
