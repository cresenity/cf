<?php

use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;

/**
 * @mixin \Symfony\Component\Mailer\SentMessage
 */
class CEmail_SentMessage implements JsonSerializable {
    use CTrait_ForwardsCalls;

    /**
     * The Symfony SentMessage instance.
     *
     * @var \Symfony\Component\Mailer\SentMessage
     */
    protected $sentMessage;

    /**
     * Create a new SentMessage instance.
     *
     * @param \Symfony\Component\Mailer\SentMessage $sentMessage
     *
     * @return void
     */
    public function __construct(SymfonySentMessage $sentMessage) {
        $this->sentMessage = $sentMessage;
    }

    /**
     * Get the underlying Symfony Email instance.
     *
     * @return \Symfony\Component\Mailer\SentMessage
     */
    public function getSymfonySentMessage() {
        return $this->sentMessage;
    }

    /**
     * Dynamically pass missing methods to the Symfony instance.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        return $this->forwardCallTo($this->sentMessage, $method, $parameters);
    }

    /**
     * Get the serializable representation of the object.
     *
     * @return array
     */
    /**
     * Ringkasan untuk log (mis. log_notification.vendor_response): id pesan, penerima envelope, debug transport.
     *
     * @return array
     */
    public function jsonSerialize() {
        $recipients = array_map(function ($address) {
            return $address->getAddress();
        }, $this->sentMessage->getEnvelope()->getRecipients());

        return [
            'message_id' => $this->sentMessage->getMessageId(),
            'recipients' => $recipients,
            'debug' => trim($this->sentMessage->getDebug()),
        ];
    }

    /**
     * @return string id pesan
     */
    public function __toString() {
        return (string) $this->sentMessage->getMessageId();
    }

    public function __serialize() {
        $originalMessage = $this->sentMessage->getOriginalMessage();
        /** @var \Symfony\Component\Mime\Email $originalMessage */
        $hasAttachments = c::collect($originalMessage->getAttachments())->isNotEmpty();

        return [
            'hasAttachments' => $hasAttachments,
            'sentMessage' => $hasAttachments ? base64_encode(serialize($this->sentMessage)) : $this->sentMessage,
        ];
    }

    /**
     * Marshal the object from its serialized data.
     *
     * @param array $data
     *
     * @return void
     */
    public function __unserialize(array $data) {
        $hasAttachments = ($data['hasAttachments'] ?? false) === true;

        $this->sentMessage = $hasAttachments ? unserialize(base64_decode($data['sentMessage'])) : $data['sentMessage'];
    }
}
