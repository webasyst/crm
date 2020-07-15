<?php

class crmSettingsSourceBlockCreateDealAction extends crmBackendViewAction
{
    /**
     * @var crmSource
     */
    protected $source;

    public function execute()
    {
        $this->accessDeniedForNotAdmin();
        $id = $this->getParameter('id') == 'create_deal_with_responsible' ? 'create_deal_with_responsible' : 'create_deal';
        $block = new crmSourceSettingsCreateDealViewBlock($id, $this->getSource(),
            array(
                'namespace' => $this->getParameter('namespace')
            )
        );
        die($block->render());
    }

    protected function getSource()
    {
        $source = $this->getParameter('source');
        if ($source instanceof crmSource) {
            return $source;
        }
        $id = (int)$this->getParameter('source');
        $source = crmSource::factory($id);
        $source->setStageId($this->getParameter('stage_id'));
        $source->setFunnelId($this->getParameter('funnel_id'));
        return $source;
    }

}
