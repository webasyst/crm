<?php

wa('webasyst');

class crmFrontendFormRegionsController extends webasystBackendRegionsController
{
    public function display()
    {
        $callback = waRequest::get('callback') ? waRequest::get('callback') : false;

        if ($callback) {
            ob_start();
        }

        parent::display();

        if ($callback) {
            $data = ob_get_contents();
            ob_end_clean();
            echo $callback."(".$data.")";
        }
    }
} 
