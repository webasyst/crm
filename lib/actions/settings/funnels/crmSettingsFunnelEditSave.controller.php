<?php

/*
 * @deprecated
 */
class crmSettingsFunnelEditSaveController extends crmJsonController
{
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
                'color'       => ifset($funnel['color']),
                'open_color'  => ifset($funnel['open_color']),
                'close_color' => ifset($funnel['close_color']),
            );
            $funnel['id'] = $fm->insert($ins);

            $number = 0;
            foreach ($stages as $s) {
                $fsm->insert(array(
                    'funnel_id' => $funnel['id'],
                    'name'      => $s['name'],
                    'number'    => $number++,
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
                    if ($dm->getByField(array('funnel_id' => $funnel['id'], 'stage_id' => $id))) {
                        throw new waException('Attempt to delete not empty stage');
                    }
                }
            }
            $upd = array(
                'name'        => $funnel['name'],
                'color'       => ifset($funnel['color']),
                'open_color'  => ifset($funnel['open_color']),
                'close_color' => ifset($funnel['close_color']),
            );
            $fm->updateById($funnel['id'], $upd);
            $number = 0;
            $ins = $upd = array();
            foreach ($stages as $s) {
                if (empty($s['id'])) {
                    $ins[] = array(
                        'funnel_id' => $funnel['id'],
                        'name'      => $s['name'],
                        'number'    => $number++,
                    );
                } else {
                    $upd[] = array(
                        'id'        => $s['id'],
                        'funnel_id' => $funnel['id'],
                        'name'      => $s['name'],
                        'number'    => $number++,
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
        $this->logAction($action, array('funnel_id' => $funnel['id']));
        //$lm = new crmLogModel();
        //$lm->log($action, wa()->getUser()->getId(), $funnel['id']);

        return $funnel['id'];
    }
}
