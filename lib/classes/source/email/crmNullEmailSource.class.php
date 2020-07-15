<?php

class crmNullEmailSource extends crmEmailSource
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
}
