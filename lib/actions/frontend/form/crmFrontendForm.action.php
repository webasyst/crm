<?php

class crmFrontendFormAction extends waViewAction
{
    public function execute()
    {
        $id = $this->getId();
        if (empty($id)) {
            $this->notFound();
        }

        $form = new crmFormRenderer($id);
        if (!$form->getForm()->exists()) {
            $this->notFound(_w('Form not found'));
        }
        $html = $form->render();
        $this->view->assign('html', $html);
        $this->view->assign('form', $form->getForm()->getInfo());
    }

    protected function getId()
    {
        return (int) $this->getRequest()->param('id', null, waRequest::TYPE_INT);
    }

    protected function notFound()
    {
        throw new waException(_w('Form not found'), 404);
    }
}
