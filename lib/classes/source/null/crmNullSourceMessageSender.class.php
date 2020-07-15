<?php

class crmNullSourceMessageSender extends crmSourceMessageSender
{
    protected function getTemplate()
    {
        return 'templates/source/message/NullSourceMessageSender.html';
    }
}
