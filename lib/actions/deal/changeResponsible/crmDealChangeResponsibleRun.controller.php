<?php

class crmDealChangeResponsibleRunController extends crmJsonController
{
    public function execute()
    {
        // Ids of contacts to merge
        $user_id = waRequest::request('user_id', 0, waRequest::TYPE_INT);
        $deal_ids = waRequest::request('deal_ids', array(), waRequest::TYPE_ARRAY_INT);

        if (!$user_id || !$deal_ids) {
            throw new waException('No deals to process.');
        }

        $deal_ids = $this->dropUnallowed($deal_ids, $user_id);

        $this->change($deal_ids, $user_id);
    }

    /**
     * Change user for given deals
     * @param array $deal_ids
     * @param int $user_id
     * @return array
     * @throws waException
     */
    protected function change($deal_ids, $user_id)
    {
        $dm = new crmDealModel();
        $dpm = new crmDealParticipantsModel();
        $lm = new crmLogModel();

        $deals = $dm->select('*')->where("id IN('".join("','", $dm->escape($deal_ids))."')")->fetchAll('id');

        $contact = new waContact($user_id);

        if (!$deals || !$contact->get('is_user')) {
            throw new waException('No deals to process.');
        }
        $d = reset($deals);
        if (!$this->getCrmRights()->funnel($d['funnel_id'])) {
            throw new waRightsException();
        }
        $sql = "UPDATE {$dm->getTableName()} SET user_contact_id = :user_id
            WHERE id IN('".join("','", $dm->escape($deal_ids))."')";
        $dm->exec($sql, array('user_id' => $user_id));

        $action_id = 'deal_transfer';

        foreach ($deals as $d) {
            $before_user = new waContact($d['user_contact_id']);
            $dpm->deleteByField(array(
                'deal_id'    => $d['id'],
                'contact_id' => $d['user_contact_id'],
                'role_id'    => 'USER',
            ));
            $dpm->replace(array(
                'deal_id'    => $d['id'],
                'contact_id' => $user_id,
                'role_id'    => 'USER',
                'label'      => null,
            ));
            $lm->log(
                $action_id,
                $d['id'] * -1,
                $d['id'],
                $before_user->getName(),
                $contact->getName(),
                null,
                ['user_id_before' => $before_user->getId(), 'user_id_after' => $contact->getId()]
            );
        }
        $this->logAction($action_id, _w('Mass action'), $contact->getId());
    }

    /**
     * @param int[] $ids
     * @param int $user_id
     * @return int[]
     * @throws waDbException
     * @throws waException
     */
    protected function dropUnallowed($ids, $user_id)
    {
        $user = new waContact($user_id);
        if (!$user->exists()) {
            return [];
        }

        $current_user_id = wa()->getUser()->getId();

        $rights = $this->getCrmRights();
        $rights_by_user = new crmRights(array('contact' => $user_id));

        $deals = $this->getDealModel()->getById($ids);
        $deals = $rights->dropUnallowedDeals($deals);

        foreach ($deals as $idx => $deal) {
            $funnel_right = $rights->funnel($deal['funnel_id']);
            $user_funnel_right = $rights_by_user->funnel($deal['funnel_id']);

            // not allowed
            if (!$user_funnel_right) {
                unset($deals[$idx]);
                continue;
            }

            $is_not_allowed = $funnel_right < crmRightConfig::RIGHT_FUNNEL_ALL && $deal['user_contact_id'] != $current_user_id &&
                    ($deal['user_contact_id'] || $funnel_right < crmRightConfig::RIGHT_FUNNEL_OWN);

            if ($is_not_allowed) {
                unset($deals[$idx]);
            }
        }

        return waUtils::getFieldValues($deals, 'id');
    }
}
