<?php

/**
 * Captures Message-ID in Swift sendPerformed — after the message was handed to the transport
 * but before SMTP/Sendmail transport calls generateId() again (Swift replaces the id for object reuse).
 */
class crmSwiftSendPerformedMessageIdPlugin implements Swift_Events_SendListener
{
    /** @var string */
    private $message_id = '';

    public function beforeSendPerformed(Swift_Events_SendEvent $evt)
    {
    }

    public function sendPerformed(Swift_Events_SendEvent $evt)
    {
        $result = $evt->getResult();
        if (
            $result !== Swift_Events_SendEvent::RESULT_SUCCESS
            && $result !== Swift_Events_SendEvent::RESULT_TENTATIVE
        ) {
            return;
        }
        $message = $evt->getMessage();
        if (!$message instanceof Swift_Mime_Message) {
            return;
        }
        $this->message_id = trim((string)$message->getId());
    }

    /**
     * @return string
     */
    public function getMessageId()
    {
        return $this->message_id;
    }
}
