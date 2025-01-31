<?php
/**
 * Saves data from DealAddParticipant dialog
 */
class crmDealAddParticipantSaveController extends crmJsonController
{
    public function execute()
    {
        $data = $this->getData();
        $participant = waRequest::post('participant', null, waRequest::TYPE_ARRAY_TRIM);

        $contact_id = $data['id'];
        if ($contact_id <= 0) {
            $contact_id = $this->saveContact($data);
            if (!empty($this->errors)) {
                return;
            }
        }

        $deal_id = $this->getDealId();

        // Check access rights
        $deal_access_level = $this->getCrmRights()->deal($deal_id);
        if ($deal_access_level <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }

        $dm = new crmDealModel();
        if ($dm->isRelated($deal_id, $contact_id, crmDealParticipantsModel::ROLE_CLIENT)) {
            $this->errors = array(
                'contact_name' => _w('Specified contact is already listed in deal')
            );
            return;
        }
        $c = new waContact($contact_id);

        if ($dm->addParticipants($deal_id, array($contact_id), crmDealParticipantsModel::ROLE_CLIENT, ifset($participant['label']))) {
            $action = 'deal_addcontact';
            $this->logAction($action, array('deal_id' => $deal_id), $contact_id);
            $lm = new crmLogModel();
            $lm->log(
                $action,
                $deal_id * -1,
                $deal_id,
                null,
                $c->getName(),
                null,
                ['contact_id' => $contact_id]
            );
        } else {
            $this->error();
        }

        $ctm = new crmContactTagsModel();
        $contact_tags = $ctm->select('*')->where("contact_id = ".(int)$contact_id)->fetchAll();
        $tm = new crmTagModel();
        $all_tags = $tm->getAll('id');
        $tags = array();
        foreach ($contact_tags as $ct) {
            $tags[$contact_id][$ct['tag_id']] = $all_tags[$ct['tag_id']]['name'];
        }
        $c['label'] = ifset($participant['label']);

        $deal = $this->getDeal();

        $deal_client = new waContact($deal['contact_id']);
        if (!$deal_client->exists()) {
            $deal_client = new waContact(0);
        }

        $counters = crmDeal::getDealPageContactCounters(
            $deal_client,
            array($c['id'] => $c),
            !empty($_order)
        );

        $can_edit_deal = $deal_access_level > crmRightConfig::RIGHT_DEAL_VIEW;

        $view = wa()->getView();
        $view->assign(array(
            'contact'       => $c,
            'is_registered' => !empty($c['password']),
            'deal'          => $dm->getById($deal_id),
            'type'          => 'contact_participant',
            'tags'          => $tags,
            'is_init_call'  => $this->getCrmRights()->isInitCall(),
            'is_sms_configured' => $this->isSMSConfigured(),
            'can_edit_deal' => $can_edit_deal,
            'counters'      => $counters
        ));

        $this->response = array(
            'deal' => array(
                'id' => $deal_id
            ),
            'html' => $view->fetch(wa()->getAppPath('templates/actions/deal/DealContact.html', 'crm')),
        );
    }

    protected function getDealId()
    {
        return (int)$this->getRequest()->request('id');
    }

    protected function getDeal()
    {
        $id = $this->getDealId();
        if ($id <= 0) {
            $this->notFound();
        }
        $deal = $this->getDealModel()->getDeal($id, true, true);
        if (!$deal) {
            $this->notFound();
        }
        return $deal;
    }

    protected function getData()
    {
        $data = $this->getRequest()->post('contact');
        $data = (array)ifset($data);
        $data['id'] = (int)ifset($data['id']);
        return $data;
    }

    protected function saveContact($data)
    {
        $controller = new crmContactSaveController(array('data' => $data));
        $controller->execute();
        $res = $controller->getExecuteResult();
        if ($res['errors']) {
            $this->errors = $this->arrayUniq($res['errors']);
            return null;
        }
        return $res['response']['contact']['id'];
    }

    protected function arrayUniq($errors)
    {
        $res = array();
        $values = array();
        foreach ($errors as $e) {
            if (empty($values[$e['value']])) {
                $values[$e['value']] = 1;
                $res[] = $e;
            }
        }
        return $res;
    }
}
