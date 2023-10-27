<?php

class crmSettingsMessageSourceAction extends crmSettingsSourceAction
{
    private $type;

    private static $allowed_types = [
        'email' => crmSourceModel::TYPE_EMAIL,
        'im' => crmSourceModel::TYPE_IM,
    ];
    
    public function execute()
    {
        $this->accessDeniedForNotAdmin();

        $this->type = waRequest::param('type', false, waRequest::TYPE_STRING_TRIM);
        if (!in_array($this->type, array_keys(self::$allowed_types))) {
            $this->notFound();
        }

        $this->view->assign(array(
            'sources' => $this->getSources(),
            'plugins' => $this->getPlugins(),
            'root_path' => $this->getConfig()->getRootPath().DIRECTORY_SEPARATOR,
            'source_type' => $this->type,
        ));
    }

    protected function getSources()
    {
        $ids = $this->getSourceModel()->select('id')
            ->where("type=:type", [ 'type' => self::$allowed_types[$this->type] ])
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

        return $this->getSourceModel()->addFunnelAndStageInfo($sources);
    }
}
