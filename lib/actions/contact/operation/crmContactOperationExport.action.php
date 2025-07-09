<?php

class crmContactOperationExportAction extends crmContactOperationAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('crm', 'export')) {
            throw new waRightsException();
        }

        $this->view->assign(array(
            'count' => $this->getCheckedCount(),
            'encoding' => crmHelper::getImportExportEncodings()
        ));

        if (wa()->whichUI() === '1.3') {
            return;
        }

        // For UI 2.0 only
        $this->setLayout();
        $is_checked_all = 0;
        $ids = waRequest::get('ids');
        if (empty($ids)) {
            $ids = [];
        } else {
            $ids = array_filter(explode(',', $ids), function ($el) {
                return is_numeric($el);
            });
        }
        $hash = waRequest::get('hash', '');
        if (!empty($hash) || empty($ids)) {
            $is_checked_all = 1;
        }
        $this->view->assign('ids', $ids);
        $this->view->assign('is_checked_all', $is_checked_all);
        $this->view->assign('hash', $hash);
    }
}
