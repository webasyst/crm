<?php
/**
 * Create new deal.
 * Also serves as a base class for DealEditSave.
 */
class crmDealSaveController extends crmJsonController
{
    public function execute()
    {
        $post_deal = $this->getRequest()->post('deal');
        $post_contact = $this->getRequest()->post('contact');
        $post_participant = $this->getRequest()->post('participant');
        $contact_action = $this->getRequest()->post('contact_action');

        $this->errors = array();
        $this->validate($post_deal);
        if ($this->errors) {
            return;
        }

        if ($contact_action == 'edit' || $contact_action == 'new') { // 'search' => nothing to do
            if ($id = $this->saveContact($post_contact, $post_deal)) {
                $post_deal['contact_id'] = $id;
            }
        }
        if (!empty($this->errors)) {
            return;
        }
        if (empty($post_deal['name'])) {
            $c = new waContact($post_deal['contact_id']);
            $post_deal['name'] = sprintf_wp('%s deal', $c->getName());
        }
        $dm = new crmDealModel();
        $deal = null;
        if (!empty($post_deal['id'])) {
            $deal = $dm->getById($post_deal['id']);
        }

        $deal_id = $this->saveDeal($post_deal, $deal);

        if (!empty($post_deal['contact_id'])) {
            $this->getDealModel()->updateParticipant($deal_id, $post_deal['contact_id'], 'contact_id', ifset($post_participant['label']));
            if ($deal && $deal['contact_id'] != $post_deal['contact_id']) {
                $dpm = new crmDealParticipantsModel();
                $dpm->deleteByField(array('deal_id' => $deal['id'], 'contact_id' => $deal['contact_id'], 'role_id' => 'CLIENT'));
            }
        }

        $this->response['deal'] = array(
            'id' => $deal_id
        );
    }

    public function validate(&$deal)
    {
        $required = array('funnel_id', 'stage_id');
        foreach ($required as $r) {
            if (empty($deal[$r])) {
                $this->errors[] = array('name' => $r, 'value' => _w('This field is required'));
            }
        }
        $deal['amount'] = preg_replace('~\s*,\s*~', '.', $deal['amount']);
        if ($deal['amount'] && !is_numeric($deal['amount'])) {
            $this->errors[] = array('name' => 'deal[amount]', 'value' => _w('Invalid value'));
        }
        if ($deal['expected_date'] && !strtotime($deal['expected_date'])) {
            $this->errors[] = array('name' => 'deal[expected_date]', 'value' => _w('Invalid value'));
        }

        // params validation cycle
        $params = !empty($deal['params']) ? $deal['params'] : array();
        $fields = crmDealFields::getAll('enabled');
        foreach ($params as $field_id => $value) {

            $value = is_scalar($value) ? (string)$value : '';

            // ignore in case of not-existing field
            if (!isset($fields[$field_id])) {
                unset($params[$field_id]);
                continue;
            }

            // emptiness of value means we must clean this parameter from deal, but not need to validate
            if (strlen($value) <= 0) {
                continue;
            }

            $field = $fields[$field_id];
            $errors = $field->validate($value);
            // no errors, good, keep going for next field
            if (empty($errors)) {
                continue;
            }

            // set first error as validate error for this field
            $error = reset($errors);
            $this->errors[] = array('name' => "deal[params][{$field_id}]", 'value' => $error);

            // unset invalid value field, just in case
            unset($params[$field_id]);
        }

        // return clean and correct params
        $deal['params'] = $params;
    }

    public function saveDeal($deal, $before_deal = [])
    {
        $dm = new crmDealModel();
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $cm = new crmContactModel();

        $is_new_deal = (!isset($deal['id']) || !$deal['id']);
        if (!$is_new_deal && $this->getCrmRights()->deal($deal['id'], true) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }
        if (!$is_new_deal) {
            $old_deal = $dm->getDeal($deal['id']);
        }

        if (!empty($deal['contact_id']) && !$this->getCrmRights()->contact($deal['contact_id'], ['access_to_not_existing' => true])) {
            if (!$is_new_deal) {
                $deal['contact_id'] = $old_deal['contact_id'];
            } else {
                $this->accessDenied();
            }
        }

        $funnel = $fm->getById($deal['funnel_id']);
        if (!$funnel) {
            $this->notFound();
        }
        if (!$this->getCrmRights()->funnel($funnel)) {
            $this->accessDenied();
        }

        $stage = $fsm->getById($deal['stage_id']);
        if (!$stage || $stage['funnel_id'] != $funnel['id']) {
            $this->notFound();
        }

        if (!empty($deal['contact_id'])) {
            $person_contact = $cm->getById($deal['contact_id']);
            if (!$person_contact) {
                $this->notFound();
            }

            $deal = array_merge($deal, array(
                'contact_id' => $person_contact['id'],
            ));
        }

        if (!empty($deal['expected_date'])) {
            $deal['expected_date'] = date('Y-m-d', strtotime($deal['expected_date']));
        }
        $deal['description'] = (string)ifset($deal['description']);

        if (isset($deal['currency_id'])) {
            $cm = new crmCurrencyModel();
            $currency = $cm->get($deal['currency_id']);
            if ($currency) {
                $deal['currency_rate'] = $currency['rate'];
            }
        }

        if ($is_new_deal) {
            $deal['id'] = $dm->add($deal);
            // $deal = $dm->getById($deal['id']);
            $action = crmDealModel::LOG_ACTION_ADD;
        } else {
            $dm->update($deal['id'], $deal, $before_deal);
            $action = crmDealModel::LOG_ACTION_UPDATE;
        }
        $this->logAction($action, array('deal_id' => $deal['id']));

        return $deal['id'];
    }

    protected function saveContact($contact, $deal)
    {
        $is_new_deal = (!isset($deal['id']) || !$deal['id']);
        if ($is_new_deal) {
            if (!$this->getCrmRights()->funnel($deal['funnel_id'], true)) {
                $this->accessDenied();
            }
        } else {
            if (!$this->getCrmRights()->contactEditable($contact['id'], ['access_to_not_existing' => true])) {
                return null;
            }
        }
        $controller = new crmContactSaveController(array('contact' => $contact));

        $controller->execute();
        $res = $controller->getExecuteResult();
        if (!empty($res['errors'])) {
            $this->errors = array_merge($this->errors, $res['errors']);
            return null;
        }
        return $res['response']['contact']['id'];
    }
}
