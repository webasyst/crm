<?php

class crmContactUiActionsMethod extends crmApiAbstractMethod
{
    public function execute()
    {
        $contact_id = $this->get('id', true);
        if (!is_numeric($contact_id) || $contact_id < 1) {
            throw new waAPIException('invalid_param', _w('Invalid contact ID.'), 400);
        }
        $contact = new waContact($contact_id);
        if (!$contact->exists()) {
            throw new waAPIException('not_found', _w('Contact not found'), 404);
        }
        if (!$this->getCrmRights()->contact($contact)) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }

        $params = ['contact_id' => $contact_id];
        $event_result = wa()->event(['crm', 'contact.ui.actions'], $params);

        $result = [];
        foreach ($event_result as $_res) {
            foreach ((array) $_res as $_key => $_items) {
                if (!empty($_items) && is_array($_items)) {
                    $result[$_key] = array_merge(ifset($result, $_key, []), $_items);
                }
            }
        }

        $result = $this->filterFields($result, ['plus_dropdown']);
        $result['plus_dropdown'] = $this->handlePlusDropdown(ifset($result['plus_dropdown']));
        $this->response = $result;
    }

    protected function handlePlusDropdown($data)
    {
        if (empty($data)) {
            return [];
        }
        $data = array_filter($data, function ($el) {
            return is_scalar($el);
        });
        if (empty($data)) {
            return [];
        }
        return array_values(array_map(function ($el) {
            return (string)$el;
        }, $data));
    }
}