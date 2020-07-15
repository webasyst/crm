<?php

class crmNullSourceMessageViewer extends crmSourceMessageSender
{
    protected function getTemplate()
    {
        return 'templates/source/message/NullSourceMessageViewer.html';
    }
}
