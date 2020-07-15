<?php
/**
 * Change existing deal name. Inline editor on DealId page.
 */
class crmDealRenameController extends crmJsonController
{
    /**
     * @var crmDealModel
     */
    protected $dm;

    public function execute()
    {
        $name = $this->getName();
        if (strlen($name) <= 0) {
            return;
        }

        $deal = $this->getDeal();
        if ($deal['name'] !== $name) {
            $this->getDealModel()->updateById($deal['id'], array('name' => $name));

            $action_id = 'deal_edit';
            $this->logAction($action_id, array('deal_id' => $deal['id']));
            $lm = new crmLogModel();
            $lm->log(
                $action_id,
                $deal['id'] * -1,
                null,
                $deal['name'],
                $name
            );
            $deal['name'] = $name;
        }
        $this->response = array(
            'deal' => $deal
        );
    }

    public function getName()
    {
        $name = $this->getRequest()->request('name');
        return trim((string)$name);
    }

    public function getDeal()
    {
        $id = (int)$this->getRequest()->request('id');
        if (!$id) {
            $this->notFound();
        }
        $deal = $this->getDealModel()->getById($id);
        if ($this->getCrmRights()->deal($deal) <= crmRightConfig::RIGHT_DEAL_VIEW) {
            $this->accessDenied();
        }
        return $deal;
    }

    /**
     * @return crmDealModel
     */
    protected function getDealModel()
    {
        return $this->dm !== null ? $this->dm : ($this->dm = new crmDealModel());
    }
}
