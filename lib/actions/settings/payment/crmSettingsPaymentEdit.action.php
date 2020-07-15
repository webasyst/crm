<?php

class crmSettingsPaymentEditAction extends crmSettingsViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('crm')) {
            throw new waRightsException();
        }
        // /webasyst/crm/?module=settings&action=paymentEdit&plugin=yandexmoney&company=1

        if (!$this->getUser()->getRights('crm', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        $instance_id = waRequest::param('instance_id', null, waRequest::TYPE_INT);
        $plugin_id = waRequest::param('plugin_id');
        $company_id = waRequest::param('company_id', null, waRequest::TYPE_INT);

        $pm = new crmPaymentModel();

        if ($instance_id) { // Edit instance

            /*
            $pm = new crmPaymentModel();
            $instance = $pm->getById($instance_id);
            */
            try {
                $instance = crmPayment::getPluginInfo($instance_id);
                $plugin_id = $instance['plugin'];
                $company_id = $instance['company_id'];
                $plugin = crmPayment::getPlugin($instance['plugin'], $instance['company_id']);
            } catch (waException $e) {
                $this->view->assign('error', $e->getMessage());
            }
        } else {
            try {
                $plugin = waPayment::factory($plugin_id);
                $plugin->company_id = $company_id;
            } catch (waException $e) {
                $this->view->assign('error', $e->getMessage());
            }
            if ($pm->getByField(array('plugin' => $plugin_id, 'company_id' => $company_id))) {
                throw new waException('Plugin already exists');
            }
            $instance = array(
                'name'        => $plugin->getName(),
                'plugin'      => $plugin_id,
                'company_id'  => $company_id,
                'logo'        => $plugin->getProperties('logo'),
                'description' => $plugin->getProperties('description'),
                'status'      => 1,
            );
        }
        $cm = new crmCompanyModel();
        $company = $cm->getById($company_id);
        if (!$company) {
            throw new waException('Company not found');
        }
        $params = array('namespace' => 'payment[settings]');
        /*
            $params = array(
                'namespace' => "payment[settings]",
                'value'     => waRequest::post('shipping[settings]'),
            );
        */
        $this->view->assign(array(
            'instance_id'   => $instance_id,
            'plugin_id'     => $plugin_id,
            'company_id'    => $company_id,
            'company'       => $company,
            'payment_html'  => $plugin->getSettingsHTML($params),
            'guide_html'    => $plugin->getGuide($params),
            'instance'      => $instance,
        ));
    }
}
