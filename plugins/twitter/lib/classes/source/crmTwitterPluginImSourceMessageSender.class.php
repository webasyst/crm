<?php

class crmTwitterPluginImSourceMessageSender extends crmImSourceMessageSender
{

    /**
     * @var crmTwitterPluginApi
     */
    protected $api;

    /**
     * @var crmTwitterPluginImSourceHelper
     */
    protected $helper;

    public function __construct(crmSource $source, $message, array $options = array())
    {
        parent::__construct($source, $message, $options);

        $this->api = new crmTwitterPluginApi($this->source->getParams());
        $this->helper = new crmTwitterPluginImSourceHelper($this->source);
        $this->message = crmTwitterPluginImSourceHelper::workupMessageForDialog($this->message);
    }

    protected function getTemplate()
    {
        return wa()->getAppPath('plugins/twitter/templates/source/message/TwitterImSourceMessageSenderDialog.html');
    }

    public function getAssigns()
    {
        return array(
            'source_id' => $this->source->getId(),
            'from_html' => $this->getFromHtml(),
            'to_html'   => $this->getToHtml()
        );
    }

    protected function getFromHtml()
    {
        $template = wa()->getAppPath('plugins/twitter/templates/source/message/FromBlock.html');
        return $this->renderTemplate($template, array(
            'message'     => $this->message,
            'plugin_icon' => $this->source->getIcon(),
        ));
    }

    protected function getToHtml()
    {
        $template = wa()->getAppPath('plugins/twitter/templates/source/message/ToBlock.html');
        return $this->renderTemplate($template, array(
            'message'     => $this->message,
            'app_icon'    => $this->getAppIcon(),
            'plugin_icon' => $this->source->getIcon(),
        ));
    }

    public function reply($data)
    {
        $errors = $this->validate($data);
        if ($errors) {
            return $this->fail($errors);
        }

        if ($this->message['params']['message_type'] == 'direct') {
            $res = $this->api->sendDirectMessasge($this->message['params']['twitter_user_id'], $data['body']);
            if (isset($res['errors']) && isset($res['errors'][0])) {
                return $this->fail($res['errors'][0]);
            }
            $message_id = $this->saveOutgoingDirectMessage($res['event']);

        } else {
            $body = '@'.$this->message['from'].' '.$data['body'];
            $res = $this->api->sendTweet($body, $this->message['params']['twitter_tweet_id']);
            if (isset($res['errors']) && isset($res['errors'][0])) {
                return $this->fail($res['errors'][0]);
            }
            $message_id = $this->saveOutgoingTweet($res);
        }
        return $this->ok(array('message_id' => $message_id));
    }

    protected function saveOutgoingDirectMessage($data)
    {
        $message = new crmTwitterPluginDirectMessage($data);
        $contact = new crmContact($this->message['contact_id']);

        $data = array(
            'creator_contact_id' => wa()->getUser()->getId(),
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'contact_id'         => $contact->getId(),
            'deal_id'            => $this->message['deal_id'],
            'subject'            => '',
            'body'               => $message->getText(),
            'from'               => $this->source->getParam('username'),
            'to'                 => $contact->get('socialnetwork.twitter', 'default'),
            'params'             => array(
                'message_type'       => 'direct',
                'twitter_message_id' => $message->getId(),
                'twitter_user_id'    => $this->message['params']['twitter_user_id'],
            )
        );
        return $this->source->createMessage($data, crmMessageModel::DIRECTION_OUT);
    }

    protected function saveOutgoingTweet($data)
    {
        $mention = new crmTwitterPluginMention($data);
        $contact = new crmContact($this->message['contact_id']);

        $data = array(
            'creator_contact_id' => wa()->getUser()->getId(),
            'transport'          => crmMessageModel::TRANSPORT_IM,
            'contact_id'         => $contact->getId(),
            'deal_id'            => $this->message['deal_id'],
            'subject'            => '',
            'body'               => $mention->getText(),
            'from'               => $this->source->getParam('username'),
            'to'                 => $contact->get('socialnetwork.twitter', 'default'),
            'params'             => array(
                'message_type'     => 'tweet',
                'twitter_tweet_id' => $mention->getId(),
                'twitter_user_id'  => $this->message['params']['twitter_user_id'],
            )
        );
        return $this->source->createMessage($data, crmMessageModel::DIRECTION_OUT);
    }

    protected function validate($data)
    {
        $body = (string)ifset($data['body']);
        if (strlen($body) <= 0) {
            return array(
                'body' => _w('This is a required field.')
            );
        }
        return array();
    }

    protected function fail($errors)
    {
        return array(
            'status' => 'fail',
            'errors' => $errors
        );
    }

    protected function ok($response)
    {
        return array(
            'status'   => 'ok',
            'response' => $response
        );
    }

    protected function getAppIcon()
    {
        $info = wa()->getAppInfo('crm');
        $sizes = array_keys($info['icon']);
        $size = min($sizes);
        return $info['icon'][$size];
    }
}