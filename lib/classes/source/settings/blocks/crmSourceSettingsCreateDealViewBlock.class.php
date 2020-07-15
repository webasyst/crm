<?php

class crmSourceSettingsCreateDealViewBlock extends crmSourceSettingsViewBlock
{
    public function getAssigns()
    {
        $assign = array();

        $id = $this->getId();
        $suffix = '_with_responsible';
        if (substr($id, -strlen($suffix)) == $suffix) {
            $responsible = new crmSourceSettingsResponsibleViewBlock('responsible', $this->source);
            $assign = $responsible->getAssigns();
        }

        return array_merge($assign, array(
            'stages' => $this->getStages(),
            'funnels' => $this->getFunnels(),
            'stage_id' => $this->getStageId(),
            'funnel_id' => $this->getFunnelId()
        ));
    }
}
