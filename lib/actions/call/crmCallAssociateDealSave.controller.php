<?php

class crmCallAssociateDealSaveController extends crmJsonController
{
    public function execute()
    {
        $call_id = waRequest::post('call_id', 0, waRequest::TYPE_INT);
        if (!$call_id) {
            throw new waException(_w('No call identifier'), 404);
        }

        $call = $this->getCallModel()->getById($call_id);

        if (empty($call)) {
            $this->notFound(_w('Call not found'));
        }

        $contact = new crmContact($call['client_contact_id']);
        if (empty($contact) || !$contact->exists()) {
            $this->notFound(_w('Contact not found'));
        }
        if (!$this->getCrmRights()->contact($contact)) {
            throw new waRightsException(_w('Access to the contact is denied.'));
        }

        $deal = waRequest::post('deal', null, waRequest::TYPE_ARRAY_TRIM);
        if (empty($deal)) {
            throw new waException(_w('No data on the deal.'), 404);
        }

        if ($deal['id'] > 0) {
            $deal = $this->getDealModel()->getDeal($deal['id'],false,true);
            if (!$deal) {
                $this->notFound(_w('Deal not found'));
            }
            if (!$this->getCrmRights()->deal($deal)) {
                $this->accessDenied();
            }

            // update call
            $this->getCallModel()->updateById($call['id'], array('deal_id' => $deal['id']));
            // update log
            $this->getLogModel()->updateByField(
                array(
                    'action' => 'call',
                    'object_id' => $call['id'],
                ),
                array(
                    'contact_id' => -$deal['id'],
                )
            );
            return;
        }

        if ($deal['id'] == 0 && intval($deal['funnel_id']) && intval($deal['stage_id']) && trim($deal['name'])) {
            // Funnel rights
            if (!$this->getCrmRights()->funnel($deal['funnel_id'])) {
                $this->accessDenied();
            }

            // Create new deal
            $id = $this->getDealModel()->add(array(
                'contact_id'      => (int) $contact['id'],
                'status_id'       => 'OPEN',
                'name'            => trim($deal['name']),
                'funnel_id'       => (int) $deal['funnel_id'],
                'stage_id'        => (int) $deal['stage_id'],
                'user_contact_id' => wa()->getUser()->getId(),
            ));
            // update call
            $this->getCallModel()->updateById($call['id'], array('deal_id' => $id));
            // update log
            $this->getLogModel()->updateByField(
                array(
                    'action' => 'call',
                    'object_id' => $call['id'],
                ),
                array(
                    'contact_id' => -$id,
                ));
            return;
        }

        $this->errors = array(_w('Unknown error'));
    }
}
