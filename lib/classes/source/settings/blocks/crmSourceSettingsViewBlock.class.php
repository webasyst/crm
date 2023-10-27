<?php

class crmSourceSettingsViewBlock extends crmViewBlock
{
    /**
     * @var crmSource
     */
    protected $source;

    public function __construct($id, crmSource $source, array $options = array())
    {
        parent::__construct($id, $options);
        $this->source = $source;
        $this->options['assign'] = (array)ifset($this->options['assign']);
        $this->options['assign']['source'] = $this->source->getInfo();

        // prevent empty funnel in case of new source
        $this->options['assign']['source']['funnel_id'] = $this->getFunnelId();
        // prevent empty stage in case of new source
        $this->options['assign']['source']['stage_id'] = $this->getStageId();

        if (!array_key_exists('namespace', $this->options['assign']) &&
                array_key_exists('namespace', $this->options)) {
            $this->options['assign']['namespace'] = $this->options['namespace'];
        }
    }

    protected function getTemplateFolder()
    {
        $source_path = wa('crm')->whichUI('crm') === '1.3' ? 'source-legacy' : 'source';
        return wa()->getAppPath('templates/'.$source_path.'/settings/blocks/', 'crm');
    }

    protected function getFunnels()
    {
        return $this->callCachedMethod(__METHOD__, 'obtainFunnels');
    }

    protected function getStagesByFunnelId($funnel_id)
    {
        return $this->callCachedMethod(__METHOD__, 'obtainStagesByFunnelId', $funnel_id);
    }


    protected function obtainFunnels()
    {
        $fm = new crmFunnelModel();
        return $fm->getAllFunnels();
    }

    protected function obtainStagesByFunnelId($funnel_id)
    {
        if ($funnel_id <= 0) {
            return array();
        }
        $fsm = new crmFunnelStageModel();
        return $fsm->getStagesByFunnel($funnel_id, true);
    }

    protected function getFunnelId()
    {
        $funnel_id = $this->source->getFunnelId();
        $funnels = $this->getFunnels();
        if (!isset($funnels[$funnel_id])) {
            $funnel_id = 0;
            if ($funnels) {
                $funnel = reset($funnels);
                $funnel_id = $funnel['id'];
            }
        }
        return $funnel_id;
    }

    protected function getStageId()
    {
        $funnel_id = $this->getFunnelId();
        $stage_id = $this->source->getStageId();
        $stages = $this->getStages($funnel_id);
        if (!isset($stages[$stage_id])) {
            $stage_id = 0;
        }
        return $stage_id;
    }

    protected function getStages()
    {
        return $this->getStagesByFunnelId($this->getFunnelId());
    }
}
