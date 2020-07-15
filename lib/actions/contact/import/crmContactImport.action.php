<?php

class crmContactImportAction extends crmContactViewAction
{
    public function execute()
    {
        if (!$this->getCrmRights()->isAdmin()) {
            $this->accessDenied();
        }

        $group_model = new waGroupModel();
        $groups = $group_model->getNames();
        $this->view->assign(array(
            'groups' => $groups,
            'encoding' => crmHelper::getImportExportEncodings()
        ));
    }
}
