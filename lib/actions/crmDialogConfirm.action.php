<?php

class crmDialogConfirmAction extends crmViewAction
{
    public function execute()
    {
        $this->view->assign(array(
            'title'     => waRequest::post('title', _w('Confirm action')),
            'text'      => waRequest::post('text', _w('Are you sure?')),
            'ok_button' => waRequest::post('ok_button', 'OK'),
        ));
        $this->setTemplate('templates/actions/DialogConfirm.html');
    }
}
