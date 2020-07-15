<?php

/**
 * Performs Shop-Script workflow actions that can be performed by backend users.
 * Called by forms returned by crmShop::workflowPrepare().
 */
class crmWorkflowPerformController extends waJsonController
{
    public function execute()
    {
        if (!($order_id = waRequest::post('id', 0, 'int'))) {
            throw new waException('No order id given.');
        }
        if (!($action_id = waRequest::post('action_id'))) {
            throw new waException('No action id given.');
        }

        wa('shop', 1);
        $workflow = new shopWorkflow();
        // @todo: check action availability in state
        $action = $workflow->getActionById($action_id);
        $this->response = $action->run($order_id);
        wa('crm', 1);
    }
}
