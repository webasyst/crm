<?php

abstract class crmContactViewAction extends crmBackendViewAction
{
    /**
     * @var bool
     */
    protected $sync_segments = true;

    public function preExecute()
    {
        $this->view->assign(array(
            'cloud'        => $this->getTagCloud(),
            'segments'     => $this->getSegments(),
            'vaults'       => $this->getVaults(),
            'responsibles' => $this->getResponsibles(),
            'own_count'    => $this->getOwnCount(),
            'is_admin'     => $this->getCrmRights()->isAdmin(),
            'merge_rights' => (wa()->getUser()->getRights('crm', 'edit')) ? true : false,
        ));

        parent::preExecute();
    }

    protected function getSegments()
    {
        $sm = new crmSegmentModel();

        if ($this->sync_segments) {
            $asm = new waAppSettingsModel();
            $time = (int)$asm->get('crm', 'segments_sync_with_categories');
            $_2m = 120;
            if (time() - $time > $_2m) {
                try {
                    $sm->syncWithCategories();
                } catch (waException $e) {
                }
                $asm->set('crm', 'segments_sync_with_categories', time());
            }
        }

        $segments = $sm->getAllSegments();

        $splintered = array(
            'my' => array(),
            'shared' => array(),
        );
        foreach ($segments as $segment) {
            if (!$segment['shared']) {
                $splintered['my'][$segment['id']] = $segment;
            } else {
                $splintered['shared'][$segment['id']] = $segment;
            }
        }
        return $splintered;
    }

    protected function getTagCloud()
    {
        $ctm = new crmTagModel();
        return $ctm->getCloud();
    }

    protected function getOwnCount()
    {
        $cm = new waContactModel();
        return $cm->countByField('crm_vault_id', $this->getOwnVaultIds());
    }

    protected function getOwnVaultIds()
    {
        $result = array();
        $adhoc_group_model = new crmAdhocGroupModel();
        foreach($adhoc_group_model->getByContact(wa()->getUser()->getId()) as $adhoc_id) {
            $result[] = -$adhoc_id;
        }
        return $result;
    }

    protected function getVaults()
    {
        return $this->getVaultModel()->getAvailable();
    }

    protected function getResponsibles()
    {
        return $this->getResponsibleModel()->getAvailableResponsibles();
    }
}
