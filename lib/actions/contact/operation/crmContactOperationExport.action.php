<?php

class crmContactOperationExportAction extends crmContactOperationAction
{
    public function execute()
    {
        $this->view->assign(array(
            'count' => $this->getCheckedCount(),
            'encoding' => crmHelper::getImportExportEncodings()
        ));
    }
}
