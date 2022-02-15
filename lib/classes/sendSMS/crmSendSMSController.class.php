<?php

class crmSendSMSController extends crmJsonController
{
    static protected $LOG_FILE = 'crm/send_sms.log';

    public function execute()
    {
        $data = $this->getData();

        $data = $this->validate($data);

        $id = $this->send($data);
        if (!$id) {
            $this->errors = array(
                'errors' => array(
                    'common' => _w("Can't send SMS")
                )
            );
        }
        $this->response = array(
            'message_id' => $id,
        );
    }

    protected function getData()
    {
        $post = waRequest::post();
        return array(
            'text' => ifset($post['text']),
            'phone' => ifset($post['phone']),
            'contact_id' => ifset($post['contact_id']),
            'hash' => ifset($post['hash']),
            'from' => ifset($post['from'])
        );
    }

    /**
     * @param $data
     * @return mixed
     * @throws waException
     */
    protected function validate($data)
    {
        if (!isset($data['text']) || !trim($data['text'])) {
            $this->notFound();
        }
        if (empty($data['phone']) && !empty($data['contact_id'])) {
            $contact = new waContact($data['contact_id']);
            $data['phone'] = $contact->get('phone', 'default');
        }
        if (empty($data['phone'])) {
            $this->notFound();
        }
        if (empty($data['contact_id'])) {
            $this->notFound();
        }
        if (empty($data['hash']) || $data['hash'] != wa()->getStorage()->get('crm_sms_send_hash')) {
            $this->notFound();
        }

        wa()->getStorage()->remove('crm_sms_send_hash');

        return $data;
    }

    protected function prepareMessageToFix($data)
    {
        return array(
            'transport'  => crmMessageModel::TRANSPORT_SMS,
            'direction'  => crmMessageModel::DIRECTION_OUT,
            'contact_id' => $data['contact_id'],
            'subject'    => null,
            'body'       => $data['text'],
            'from'       => $data['from'],
            'to'         => $data['phone'],
        );
    }

    protected function send($data)
    {
        try {
            $sms = new crmSMS();

            $res = $sms->send($data['phone'], $data['text'], $data['from']);

            if ($res == false) {
                return false;
            }

            $mm = new crmMessageModel();
            $message = $this->prepareMessageToFix($data);

            $id = $mm->fix($message, array(
                'wa_log' => true
            ));

            if (waSystemConfig::isDebug()) {
                waLog::log('sms to number '.$data['phone'].' "'.$data['text'].'" sent', self::$LOG_FILE);
            }

            return $id;

        } catch(waException $e) {
        }
    }
}
