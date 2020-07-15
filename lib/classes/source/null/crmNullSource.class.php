<?php

class crmNullSource extends crmSource
{
    public function save($data, $delete_old_params = false)
    {
        return;
    }

    public function workupInfo($info)
    {
        $info['disabled'] = 1;
        return $info;
    }

    public function canWork()
    {
        return false;
    }

    public function testConnection()
    {
        return array('' => 'Connection failed');
    }
}
