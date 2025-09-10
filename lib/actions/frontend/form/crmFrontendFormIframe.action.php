<?php

class crmFrontendFormIframeAction extends waViewAction
{
    public function execute()
    {
        $id = $this->getId();
        if ($id <= 0) {
            $this->notFound();
        }

        $form = new crmFormRenderer($id);
        if (!$form->getForm()->exists()) {
            $this->notFound(_w('Form not found'));
        }
        $html = $form->render(true);
        $this->view->assign('html', $html);

        header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
    }

    protected function getId()
    {
        return (int) $this->getRequest()->param('id');
    }

    protected function notFound()
    {
        throw new waException(_w('Form not found'), 404);
    }
}
