<?php

class crmSettingsLostReasonsSaveController extends crmJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }

        $reasons = $this->getRequest()->post('reasons', array(), waRequest::TYPE_ARRAY_TRIM);
        $lost_reason_require = $this->getRequest()->post('lost_reason_require');
        $lost_reason_freeform = $this->getRequest()->post('lost_reason_freeform');

        $this->saveData($reasons);

        $asm = new waAppSettingsModel();
        $asm->set('crm', 'lost_reason_require', $lost_reason_require ? 1 : 0);
        $asm->set('crm', 'lost_reason_freeform', $lost_reason_freeform ? 1 : 0);
    }

    protected function saveData($reasons)
    {
        $dlm = new crmDealLostModel();

        $old_ids = $dlm->select('id')->fetchAll('id', true);

        $new_ids = array();
        $sort = 0;
        foreach ($reasons as $r) {
            $row = array(
                'funnel_id' => $r['funnel_id'],
                'name' => $r['name'],
                'sort' => $sort++,
            );
            if (empty($r['id'])) {
                $dlm->insert($row);
            } else {
                $dlm->updateById($r['id'], $row);
                $new_ids[$r['id']] = 1;
            }
        }
        if ($to_delete = array_diff(array_keys($old_ids), array_keys($new_ids))) {
            $dlm->exec("DELETE FROM {$dlm->getTableName()} WHERE id IN('".join("','", $dlm->escape($to_delete))."')");
        }
    }
}
