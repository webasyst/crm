<?php
class crmSettingsPaymentDeleteController extends waJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        if ($instance_id = waRequest::post('id')) {
            $model = new crmPaymentModel();
            if ($plugin = $model->getById($instance_id)) {
                $settings_model = new crmPaymentSettingsModel();
                $settings_model->del($plugin['id'], null);
                $model->deleteById($plugin['id']);
            } else {
                throw new waException("Payment plugin {$instance_id} not found", 404);
            }

        }
    }
}
