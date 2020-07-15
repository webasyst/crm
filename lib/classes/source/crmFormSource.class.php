<?php

class crmFormSource extends crmSource
{
    const MESSAGE_TO_VARIANT_CLIENT = 'client';
    const MESSAGE_TO_VARIANT_RESPONSIBLE_USER = 'responsible_user';
    protected $type = crmSourceModel::TYPE_FORM;
}
