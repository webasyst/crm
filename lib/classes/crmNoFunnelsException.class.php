<?php
class crmNoFunnelsException extends waException
{
    public function __construct()
    {
        parent::__construct('Please set up at least one funnel', 500);
    }
}
