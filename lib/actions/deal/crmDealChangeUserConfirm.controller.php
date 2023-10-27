<?php

class crmDealChangeUserConfirmController extends crmJsonController
{
    public function execute()
    {
        $deal = $this->getDeal();
        if (!$this->hasAccessToDeal($deal)) {
            $this->accessDenied();
        }

        $ui = wa('crm')->whichUI('crm');
        $user_contact_id = $this->getUserContactId();
        if ($user_contact_id != $this->getUserId()) {
            // emulated change user
            $updated_deal = array_merge($deal, [
                'user_contact_id' => $user_contact_id
            ]);
            if (!$this->hasAccessToDeal($updated_deal)) {
                $this->response = [
                    'need_confirm' => true,
                    'html' => $this->renderConfirmDialog($deal, $user_contact_id, $ui)
                ];
                return;
            }
        }

        $this->response = [
            'need_confirm' => false
        ];
    }

    protected function getUserContactId()
    {
        return (int)waRequest::post('user_contact_id', null, waRequest::TYPE_INT);
    }

    public function getDeal()
    {
        $id = (int)$this->getRequest()->request('id');
        if (!$id) {
            $this->notFound();
        }
        return $this->getDealModel()->getById($id);
    }

    protected function hasAccessToDeal($deal)
    {
        return $this->getCrmRights()->deal($deal) > crmRightConfig::RIGHT_DEAL_VIEW;
    }

    public function renderConfirmDialog(array $deal, $user_contact_id, $ui = '1.3')
    {
        $current_deal_user = null;
        if ($deal['user_contact_id']) {
            $current_deal_user = new waContact($deal['user_contact_id']);
            if (!$current_deal_user->exists()) {
                $current_deal_user = null;
            }
        }

        $future_deal_user = new waContact($user_contact_id);
        if (!$future_deal_user->exists()) {
            $this->notFound(_w('User not found.'));
        }

        $actions_path = ($ui === '1.3' ? 'actions-legacy' : 'actions');
        $template = wa()->getAppPath('templates/'.$actions_path.'/deal/DealChangeUserConfirm.html', 'crm');
        return $this->renderTemplate($template, [
            'deal' => $deal,
            'current_deal_user' => $current_deal_user,
            'future_deal_user' => $future_deal_user
        ]);
    }
}
