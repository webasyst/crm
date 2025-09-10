<?php

class crmReviewWidgetController extends waController
{
    public function execute()
    {
        $installer_app = wa()->getView()->getHelper()->installer;
        if ($installer_app && method_exists($installer_app, 'reviewWidget')) {
            echo $installer_app->reviewWidget('app/crm');
        }
    }
}