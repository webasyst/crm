<?php

class crmSettingsFunnelSaveController extends crmJsonController
{
    private $available_groups;

    public function execute()
    {
        $funnel = $this->getRequest()->post('funnel', array(), waRequest::TYPE_ARRAY_TRIM);
        $stages = $this->getRequest()->post('stages', array(), waRequest::TYPE_ARRAY_TRIM);

        $errors = $this->validate($funnel, $stages);
        if ($errors) {
            $this->errors = $errors;
            return;
        }
        $id = $this->saveData($funnel, $stages);

        $fields_params = $this->getDealFieldParams();
        if ($id > 0 && $fields_params) {
            $this->saveDealFieldsParameters($id, $fields_params);
        }

        $this->response = array(
            'id' => $id
        );
    }

    protected function validate($funnel, $stages)
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        $errors = array();

        $required = array('name');
        foreach ($required as $r) {
            if (empty($funnel[$r])) {
                $errors['funnel['.$r.']'] = _w('This field is required');
            }
            foreach ($stages as $i => $s) {
                if (empty($stages[$i][$r])) {
                    $errors['stages['.$i.']['.$r.']'] = _w('This field is required');
                }
            }
        }
        $this->available_groups = crmHelper::getAvailableGroups('funnel.'.$funnel['id']);

        if (array_diff_key($funnel['groups'], $this->available_groups)) {
            throw new waRightsException();
        }
        return $errors;
    }

    protected function saveData($funnel, $stages)
    {
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();
        $dm = new crmDealModel();

        if (empty($funnel['id'])) {
            $action = 'funnel_add';
            $ins = array(
                'name'        => $funnel['name'],
                'sort'        => 0,
                'icon'        => ifset($funnel['icon']),
                'color'       => ifset($funnel['color']),
                'open_color'  => ifset($funnel['open_color']),
                'close_color' => ifset($funnel['close_color']),
            );
            $funnel['id'] = $fm->insert($ins);

            $number = 0;
            foreach ($stages as $s) {
                $limit_hours = intval($s['limit_hours']);
                $fsm->insert(array(
                    'funnel_id'   => $funnel['id'],
                    'name'        => $s['name'],
                    'number'      => $number++,
                    'limit_hours' => $limit_hours ? $limit_hours : null,
                ));
            }
        } else {
            $action = 'funnel_edit';

            if (!empty($funnel['id']) && !($old_funnel = $fm->getById($funnel['id']))) {
                throw new waException(_w('Note not found'));
            }
            $old_stages = $fsm->getStagesByFunnel($funnel['id']);
            $edited_stages = array();
            foreach ($stages as $s) {
                if (!empty($s['id'])) {
                    if (!empty($edited_stages[$s['id']])) { // check if ID is uniq
                        throw new waException('Invalid stages');
                    }
                    $edited_stages[$s['id']] = $s;
                }
            }
            if (array_diff(array_keys($edited_stages), array_keys($old_stages))) { // check if IDs belong to this funnel
                throw new waException('Invalid stages');
            }
            $to_delete = array_diff(array_keys($old_stages), array_keys($edited_stages));
            if ($to_delete) {
                foreach ($to_delete as $id) {
                    if ($dm->getByField(array('funnel_id' => $funnel['id'], 'stage_id' => $id, 'status_id' => crmDealModel::STATUS_OPEN))) {
                        throw new waException('Attempt to delete not empty stage');
                    }
                }
            }
            $upd = array(
                'name'        => $funnel['name'],
                'icon'        => ifset($funnel['icon']),
                'color'       => ifset($funnel['color']),
                'open_color'  => ifset($funnel['open_color']),
                'close_color' => ifset($funnel['close_color']),
            );
            $fm->updateById($funnel['id'], $upd);
            $number = 0;
            $ins = $upd = array();
            foreach ($stages as $s) {
                $limit_hours = intval($s['limit_hours']);
                if (empty($s['id'])) {
                    $ins[] = array(
                        'funnel_id'   => $funnel['id'],
                        'name'        => $s['name'],
                        'number'      => $number++,
                        'limit_hours' => $limit_hours ? $limit_hours : null,
                    );
                } else {
                    $upd[] = array(
                        'id'          => $s['id'],
                        'funnel_id'   => $funnel['id'],
                        'name'        => $s['name'],
                        'number'      => $number++,
                        'limit_hours' => $limit_hours ? $limit_hours : null,
                    );
                }
            }
            $fsm->deleteByField('funnel_id', $funnel['id']);
            if ($ins) {
                $fsm->multipleInsert($ins);
            }
            if ($upd) {
                $fsm->multipleInsert($upd);
            }
        }

        $crm = new waContactRightsModel();

        foreach ($funnel['groups'] as $id => $rights) {
            if ($rights > 0 || $this->available_groups[$id]['rights'] > 0) {
                $crm->save($id * -1, 'crm', 'funnel.'.$funnel['id'], $rights);
                $crm->save($id * -1, 'crm', 'backend', 1);
            }
        }

        $this->logAction($action, array('funnel_id' => $funnel['id']));
        //$lm = new crmLogModel();
        //$lm->log($action, wa()->getUser()->getId(), $funnel['id']);

        return $funnel['id'];
    }

    /**
     * @return array|mixed
     */
    protected function getDealFieldParams()
    {
        $deal_field_params = $this->getRequest()->post('deal_field_params');
        if (is_array($deal_field_params)) {
            return $deal_field_params;
        }
        return [];
    }

    protected function saveDealFieldsParameters($funnel_id, array $deal_field_params)
    {
        foreach ($deal_field_params as $_deal_field_id => $params) {
            $field = crmDealFields::get($_deal_field_id);
            if (!$field) {
                continue;
            }

            $funnel_parameters = [];
            foreach ($params as $param_name => $param_value) {
                if ($param_value) {
                    $funnel_parameters[$param_name] = 1;
                } else {
                    unset($funnel_parameters[$param_name]);
                }
            }

            if ($field->setFunnelParameters($funnel_id, $funnel_parameters)) {
                crmDealFields::updateField($field);
            }
        }
    }

}
