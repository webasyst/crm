<?php

class crmSendSMSDialogAction extends crmBackendViewAction
{
    public function __construct($params = null)
    {
        parent::__construct($params);

        $actions_path = wa('crm')->whichUI('crm') === '1.3' ? 'actions-legacy' : 'actions';
        $this->setTemplate('templates/' . $actions_path . '/message/MessageSendSMSDialog.html');
    }

    public function preExecute()
    {
        $phone = $this->getPhone();
        $phone_formatted = $this->formatPhone($phone);

        $hash = md5(mt_rand().mt_rand().uniqid(wa()->getUser()->getId().get_class($this), true).mt_rand());
        wa()->getStorage()->set('crm_sms_send_hash', $hash);

        $this->view->assign(array(
            'phone'           => $phone,
            'phone_formatted' => $phone_formatted,
            'contact'         => $this->getContact(),
            'hash'            => $hash,
            'send_action_url' => $this->getSendActionUrl(),
            'sms_senders'     => $this->getSMSSenders()
        ));
    }

    protected function getPhone()
    {
        $phone = $this->getRequest()->request('phone');
        return $phone;
    }

    protected function getContactId()
    {
        return (int)$this->getRequest()->request('contact_id');
    }

    /**
     * @return crmContact
     * @throws waException
     */
    protected function getContact()
    {
        return new crmContact($this->getContactId());
    }

    protected function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $phone_field = new waContactPhoneField('', '');
        return $phone_field->format($phone, 'value');
    }

    public function getSendActionUrl()
    {
        return null;
    }

    protected function getSMSSenders()
    {
        $sms_from = array(
            '' => array(
                'name' => _w('System default'),
            ),
        );

        if (!waSMS::adapterExists()) {
            return $sms_from;
        }

        $sms_config = wa()->getConfig()->getConfigFile('sms');
        
        // sender '*' in CRM names "System default", so in foreach skip '*'

        foreach ($sms_config as $from => $options) {
            if ($from != '*') {
                $sms_from[$from] = array(
                    'name' => $from . ' (' . $options['adapter'] . ')'
                );
            }
        }

        $sms_from['specified'] = array(
            'name' => _w('Specified'),
        );

        return $sms_from;

    }

}
