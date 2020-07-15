<?php

/**
 * Dialog HTML to modify user of given deals
 */
class crmDealChangeResponsibleAction extends crmBackendViewAction
{
    public function execute()
    {
        $ids = preg_split('~\s*,\s*~', waRequest::request('ids'));

        $allowed_ids = $this->dropUnallowed($ids);

        $dropped_ids_count = count($ids) - count($allowed_ids);
        $ids = $allowed_ids;

        $dm = new crmDealModel();

        $deals = $dm->select('*')->where("id IN('".join("','", $dm->escape($ids))."')")->fetchAll('id');

        $c = new waContactsCollection('users');
        $all_users = $c->getContacts(wa('crm')->getConfig()->getContactFields());
        uasort($all_users, wa_lambda('$a, $b', 'return strcmp($a["name"], $b["name"]);'));

        $users = array();
        foreach ($all_users as $u) {
            $r = new crmRights([ 'contact' => $u['id'] ]);

            $must_present = true;
            foreach ($deals as $d) {
                if (!$r->funnel($d['funnel_id'])) {
                    $must_present = false;
                    break;
                }
            }

            if ($must_present) {
                $users[$u['id']] = new crmContact($u);
            }
        }

        $this->view->assign(array(
            'deals' => $deals,
            'users' => $users,
            'dropped_ids_count' => $dropped_ids_count
        ));
    }

    /**
     * @param int[] $ids
     * @return int[]
     * @throws waDbException
     * @throws waException
     */
    protected function dropUnallowed($ids)
    {
        return $this->getCrmRights()->dropUnallowedDeals($ids);
    }
}
