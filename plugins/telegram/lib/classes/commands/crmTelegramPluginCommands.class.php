<?php

class crmTelegramPluginCommands
{
    /**
     * @var crmMessageModel
     */
    protected $mm;

    /**
     * @var crmTelegramPluginImSourceMessageSender
     */
    protected $source_sender;

    /**
     * @var crmSource
     */
    protected $source;

    /**
     * @var crmContact
     */
    protected $contact;

    protected $telegram_message;

    protected $crm_message;

    /**
     * crmTelegramPluginCommands constructor.
     * @param crmSource $source
     * @param crmContact $contact
     * @param array $telegram_message
     * @param int $crm_message_id
     */
    public function __construct(crmSource $source, crmContact $contact, $telegram_message, $crm_message_id)
    {
        $this->source = $source;
        $this->contact = $contact;
        $this->telegram_message = $telegram_message;
        $this->crm_message = $this->getMessageModel()->getMessage($crm_message_id);
        $this->source_sender = $this->getSourceSender();
    }

    /**
     * Send reply on /start message
     */
    public function start()
    {
        if ($this->telegram_message['text'] === '/start' && $this->source->getParam('start_response')) {
            $start_text = $this->replaceVars($this->source->getParam('start_response'));
            $this->source_sender->reply(array('body' => $start_text));
        }
    }

    /**
     * Set the value of some variables when sending a prepared answer.
     * @param string $content
     * @return string
     */
    protected function replaceVars($content)
    {
        $vars = array(
            '$contact_name' => $this->contact->getName(),
            '$site_name'    => wa()->accountName(),
            '$site_url'     => wa()->getRootUrl(true),
            '$site_link'    => '<a href="'.wa()->getRootUrl(true).'">'.wa()->accountName().'</a>',
            '$bot_name'     => $this->source->getParam('firstname'),
            '$bot_username' => $this->source->getParam('username'),
        );

        return strtr($content, $vars);
    }

    /**
     * @return crmMessageModel
     */
    protected function getMessageModel()
    {
        return $this->mm !== null ? $this->mm : ($this->mm = new crmMessageModel());
    }

    /**
     * @return crmTelegramPluginImSourceMessageSender
     */
    protected function getSourceSender()
    {
        return $this->source_sender !== null ? $this->source_sender : ($this->source_sender = new crmTelegramPluginImSourceMessageSender($this->source, $this->crm_message));
    }
}