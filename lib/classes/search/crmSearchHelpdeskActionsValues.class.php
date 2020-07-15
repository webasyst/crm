<?php

class crmContactsSearchHelpdeskActionsValues
{
    private $workflows;
    private function getActions()
    {
        $workflows = $this->getWorkflows();

        $human_values = array();
        $other_values = array();
        foreach (
            wao(new waModel())->query("SELECT DISTINCT action_id, workflow_id FROM `helpdesk_request_log`")
            as $row)
        {
            $wf_id = $row['workflow_id'];
            $action_id = $row['action_id'];

            $r = $this->getNameOfVal($row);
            if ($r['human']) {
                $human_values["{$wf_id}@{$action_id}"] = array(
                    'value' => "{$wf_id}@{$action_id}",
                    'name' => $r['name']
                );
            } else {
                $other_values["{$wf_id}@{$action_id}"] = array(
                    'value' => "{$wf_id}@{$action_id}",
                    'name' => $r['name']
                );
            }
        }

        uasort($human_values, wa_lambda('$a, $b', 'return strcmp($a["name"], $b["name"]);'));
        uasort($other_values, wa_lambda('$a, $b', 'return strcmp($a["name"], $b["name"]);'));

        return $human_values + $other_values;;

    }

    private function getNameOfVal($val)
    {
        $action_id = $val['action_id'];
        $wf_id = $val['workflow_id'];
        $workflows = $this->getWorkflows();
        $name = '';
        $human = false;
        if (isset($workflows[$wf_id])) {
            $wf = $workflows[$wf_id];
            try {
                $action = $wf->getActionById($action_id);
                $name = htmlspecialchars($wf->getName()) . ' / ' . htmlspecialchars($action->getName());
                $human = true;
            } catch (Exception $e) {

            }

            if (!$human) {
                if ($action_id && $action_id[0] === '!') {
                    $name = helpdeskRequest::getSpecialActionName($action_id);
                    if ($wf) {
                        $name = htmlspecialchars($wf->getName()) . ' / ' . $name;
                    }
                    $human = true;
                } else {
                    $name = htmlspecialchars($action_id);
                    if ($wf) {
                        $name = htmlspecialchars($wf->getName()) . ' / ' . $name;
                    }
                    $human = false;
                }
            }
        }
        return array('human' => $human, 'name' => $name ? $name : "{$wf_id}@{$action_id}");
    }

    private function getWorkflows()
    {
        if ($this->workflows === null) {
            wa('helpdesk');
            $this->workflows = helpdeskWorkflow::getWorkflows();
        }
        return $this->workflows;
    }


    public function getValues()
    {
        return array_values($this->getActions());
    }

    private function unwrapValItem($val_item)
    {
        $val = '';
        if (is_array($val_item)) {
            if (isset($val_item['val'])) {
                $val = $val_item['val'];
            } else {
                $key = key($val_item);
                $val_item = $val_item[$key];
                $val = $key;
            }
        } else {
            $val = $val_item;
        }
        if ($val) {
            $val = explode('@', $val);
            $workflow_id = null;
            if (isset($val[1])) {
                $workflow_id = $val[0];
                $action_id = $val[1];
            } else {
                $action_id = $val[0];
            }
            return array('workflow_id' => $workflow_id, 'action_id' => $action_id);
        }
        return null;
    }


    public function where($val_item)
    {
        $val = $this->unwrapValItem($val_item);
        if ($val) {
            $m = new waModel();
            if (isset($val['workflow_id']) && ($val['workflow_id'] || $val['workflow_id'] === '0' || $val['workflow_id'] === 0)) {
                $workflow_id = $m->escape($val['workflow_id']);
                $action_id = $m->escape($val['action_id']);
                return ":parent_table.workflow_id = '{$workflow_id}' AND :parent_table.action_id = '{$action_id}'";
            } else {
                $action_id = $m->escape($val['action_id']);
                return ":parent_table.action_id = '{$action_id}'";
            }

        }
        return '';
    }

    public function extra($val_item)
    {
        $val = $this->unwrapValItem($val_item);
        if ($val) {
            if (isset($val['workflow_id']) && ($val['workflow_id'] || $val['workflow_id'] === '0' || $val['workflow_id'] === 0)) {
                $r = $this->getNameOfVal($val);
                return array('name' => $r['name']);
            }
        }
        return array('name' => '');
    }

}
