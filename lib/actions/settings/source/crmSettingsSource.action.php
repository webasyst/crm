<?php

class crmSettingsSourceAction extends crmSettingsViewAction
{
    public function execute()
    {
        $this->accessDeniedForNotAdmin();

        $type = waRequest::get('type', false, waRequest::TYPE_STRING_TRIM);
        $allowed_types = array('email', 'form', 'im');
        if (!in_array($type, $allowed_types)) {
            $type = false;
        }

        $this->view->assign(array(
            'forms' => $this->getFormModel()->getAllFormsForControllers(),
            'sources' => $this->getSources(),
            'plugins' => $this->getPlugins(),
            'root_path' => $this->getConfig()->getRootPath().DIRECTORY_SEPARATOR,
            'source_type' => $type,
        ));
    }

    protected function getSources()
    {
        $sm = new crmSourceModel();
        $ids = $sm->select('id')
            ->where("type NOT IN(:types)", array(
                'types' => array(crmSourceModel::TYPE_SHOP, crmSourceModel::TYPE_FORM)
            ))
            ->fetchAll(null, true);

        if (!$ids) {
            return array();
        }

        $sources = array();
        foreach ($ids as $id) {
            $source = crmSource::factory($id);
            $sources[$id] = $source->getInfo();
            $sources[$id]['icon_url'] = $source->getIcon();
        }

        $sources = $this->getSourceModel()->addFunnelAndStageInfo($sources);
        $grouped = array();
        foreach ($sources as $source) {
            $grouped[$source['type']][$source['id']] = $source;
        }

        return $grouped;
    }

    protected function getPlugins()
    {
        $plugins = array();

        /**
         * @var crmSourcePlugin[] $instances
         */
        $instances = (array)crmSourcePlugin::factory();
        foreach ($instances as $plugin_id => $instance) {
            $source = $instance->factorySource(0);
            $info = $instance->getInfo();
            $info['source'] = $source->getInfo();
            $plugins[$plugin_id] = $info;
        }

        return $plugins;
    }
}
