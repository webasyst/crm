<?php

class crmDealRoleListMethod extends crmApiAbstractMethod
{
    public function execute()
    {
        $scope = strtoupper($this->get('scope', true));

        if (!in_array($scope, [crmDealParticipantsModel::ROLE_USER, crmDealParticipantsModel::ROLE_CLIENT])) {
            throw new waAPIException('unknown_value', sprintf_wp('Unknown “%s” value.', 'role_id'), 400);
        }

        $labels = $this->getDealParticipantsModel()->query("
            SELECT label FROM crm_deal_participants
            WHERE role_id = s:role AND label IS NOT NULL AND label <> ''
            GROUP BY label ORDER BY COUNT(*) DESC
        ", ['role' => $scope])->fetchAll();

        $this->response = array_column($labels, 'label');
    }
}
