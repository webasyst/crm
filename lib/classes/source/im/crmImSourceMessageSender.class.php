<?php

abstract class crmImSourceMessageSender extends crmSourceMessageSender
{
    protected function getTemplate()
    {
        return 'templates/source/message/ImSourceMessageSender.html';
    }
}
