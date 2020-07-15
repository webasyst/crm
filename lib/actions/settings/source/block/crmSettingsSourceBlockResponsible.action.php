<?php

class crmSettingsSourceBlockResponsibleAction extends crmBackendViewAction
{
    /**
     * @var crmSource
     */
    protected $source;

    public function execute()
    {
        $this->accessDeniedForNotAdmin();
        $block = new crmSourceSettingsResponsibleViewBlock('responsible', $this->getSource(),
            array(
                'namespace' => $this->getParameter('namespace'),
                'check_user_funnel_right' => true,
                'group_id' => $this->getParameter('group_id')
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

        $funnel_id = (int)$this->getParameter('funnel_id');
        if ($funnel_id > 0) {
            $source->setFunnelId($funnel_id);
            $source->setParam('create_deal', 1);
        } else {
            $source->setParam('create_deal', 0);
        }
        $source->setResponsibleContactId($this->getParameter('responsible_contact_id'));
        return $source;
    }

}
