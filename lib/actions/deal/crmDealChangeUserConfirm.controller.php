<?php

class crmDealChangeUserConfirmController extends crmJsonController
{
    public function execute()
    {
        $deal = $this->getDeal();
        if (!$this->hasAccessToDeal($deal)) {
            $this->accessDenied();
        }

        $user_contact_id = $this->getUserContactId();

        if ($user_contact_id != $this->getUserId()) {
            // emulated change user
            $updated_deal = array_merge($deal, [
                'user_contact_id' => $user_contact_id
            ]);
            if (!$this->hasAccessToDeal($updated_deal)) {
                $this->response = [
                    'need_confirm' => true,
                    'html' => $this->renderConfirmDialog($deal, $user_contact_id)
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

    protected function renderConfirmDialog(array $deal, $user_contact_id)
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

        $template = wa()->getAppPath('templates/actions/deal/DealChangeUserConfirm.html', 'crm');
        return $this->renderTemplate($template, [
            'deal' => $deal,
            'current_deal_user' => $current_deal_user,
            'future_deal_user' => $future_deal_user
        ]);
    }
}
