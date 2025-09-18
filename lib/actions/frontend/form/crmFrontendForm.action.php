<?php

class crmFrontendFormAction extends waViewAction
{
    public function execute()
    {
        $hash = waRequest::param('hash', null, waRequest::TYPE_STRING);
        $form_id = substr($hash, 16, -16);
        if (empty($form_id)) {
            $this->notFound();
        }
        
        $form = new crmFormRenderer($form_id);
        if (!$form->getForm()->exists() || $hash != $form->getForm()->getHash()) {
            $this->notFound(_w('Form not found'));
        }

        $html = $form->render();
        $this->view->assign('html', $html);
        $this->view->assign('form', $form->getForm()->getInfo());
    }

    protected function notFound()
    {
        throw new waException(_w('Form not found'), 404);
    }
}
