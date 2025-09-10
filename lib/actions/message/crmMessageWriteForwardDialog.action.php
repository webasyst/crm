<?php

class crmMessageWriteForwardDialogAction extends crmSendEmailDialogAction
{
    /**
     * @var array
     */
    protected $message;

    public function execute()
    {
        $dm = new crmDealModel();
        $fm = new crmFunnelModel();
        $fsm = new crmFunnelStageModel();

        $participants_ids = array();
        $deal = $this->getDeal();
        if ($deal) {
            $participants_ids = $this->getParticipantsIds($deal['participants']);
        } else {
            $funnel = $fm->getAvailableFunnel();
            if (!$funnel) {
                $this->accessDenied();
            }
            $stage_id = $fsm->select('id')->where(
                'funnel_id = '.(int)$funnel['id']
            )->order('number')->limit(1)->fetchField('id');

            // Just empty deal, for new message
            $now = date('Y-m-d H:i:s');
            $deal = $dm->getEmptyDeal();
            $deal = array_merge($deal, array(
                'creator_contact_id' => wa()->getUser()->getId(),
                'create_datetime'    => $now,
                'update_datetime'    => $now,
                'funnel_id'          => $funnel['id'],
                'stage_id'           => $stage_id,
            ));
        }

        $funnels = $fm->getAllFunnels(true);
        if (empty($funnels[$deal['funnel_id']])) {
            throw new waException('Funnel not found');
        }
        $stages = $fsm->getStagesByFunnel($funnels[$deal['funnel_id']]);

        $this->view->assign(array(
            'deal'            => $deal,
            'stages'          => $stages,
            'funnels'         => $funnels,
            'participants'    => $this->getParticipantsData($participants_ids),
            'contact'         => null,
            'email'           => null,
            'files'           => $this->getFiles(),
            'subject'         => $this->getSubject(),
            'hidden_params'   => $this->getHiddenParams(),
            'send_action_url' => wa()->getAppUrl('crm') . '?module=message&action=sendForward',
            'action'          => self::ACT_FORWARD_MESSAGE,
        ));
    }

    /**
     * @return string
     */
    protected function getSubject()
    {
        $message = $this->getMessage();
        $prefix = substr($message['subject'], 0, 3);
        if (strtolower($prefix) === 'fw:') {
            return $message['subject'];
        }
        return 'Fw: ' . $message['subject'];
    }

    /**
     * @return string
     */
    protected function getBody()
    {
        $message = $this->getMessage();
        $create_datetime = $message['create_datetime'];
        $body = $message['body_sanitized'];
        $contact = new crmContact($message['creator_contact_id']);
        $name = htmlspecialchars($contact->getName());
        $text = _w('<p><br></p></b><br><section data-role="c-email-signature">:SIGNATURE:</section><p><br></p><p>:MESSAGE_TIME:, :CLIENT: wrote:</p><blockquote>:BODY:</blockquote>');
        $text = str_replace(':MESSAGE_TIME:', wa_date('datetime', $create_datetime), $text);
        $text = str_replace(':CLIENT:', $name, $text);
        $text = str_replace(':BODY:', $body, $text);
        $text = str_replace(':SIGNATURE:', $this->getUserContact()->getEmailSignature(), $text);
        return $text;
    }

    protected function getHiddenParams()
    {
        $message = $this->getMessage();
        return array(
            'message_id' => $message['id']
        );
    }

    protected function getParticipantsIds($deal_participants)
    {
        if (!$deal_participants) {
            return array();
        }

        $ids = array();
        foreach ($deal_participants as $participant) {
            $ids[] = $participant['contact_id'];
        }

        // Delete the current user from the list
        if (in_array(wa()->getUser()->getId(), $ids)) {
            unset($ids[array_search(wa()->getUser()->getId(), $ids)]);
        }
        return $ids;
    }
}
