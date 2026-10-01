<?php

/**
 * Pesan jalur lama (CEmail::sender(), mailer->html()/raw()) yang ditangkap CEmail::fake(), berbentuk Mailable
 * supaya hasTo()/hasSubject()/hasCc() dkk bisa dipakai di assertion.
 */
class CEmail_Mailable_LegacyMailable extends CEmail_Mailable {
    /**
     * @var null|CEmail_Message
     */
    protected $message;

    /**
     * @var mixed
     */
    protected $body;

    /**
     * @var string
     */
    protected $bodyType = 'html';

    /**
     * @param CEmail_Message $message
     * @param mixed          $body     html/teks mentah, atau nama view bila pengirim memakai view
     * @param string         $bodyType html, raw, plain, atau view
     *
     * @return static
     */
    public static function fromMessage(CEmail_Message $message, $body = null, $bodyType = 'html') {
        $mailable = new static();
        $mailable->message = $message;
        $mailable->body = $body;
        $mailable->bodyType = $bodyType;

        $symfony = $message->getSymfonyMessage();
        foreach (['from', 'to', 'cc', 'bcc', 'replyTo'] as $type) {
            foreach ($symfony->{'get' . ucfirst($type)}() as $address) {
                $mailable->{$type}($address->getAddress(), strlen((string) $address->getName()) > 0 ? $address->getName() : null);
            }
        }
        if ($symfony->getSubject() !== null) {
            $mailable->subject($symfony->getSubject());
        }
        if ($bodyType === 'html') {
            $mailable->html((string) $body);
        }

        return $mailable;
    }

    /**
     * @return null|CEmail_Message
     */
    public function getMessage() {
        return $this->message;
    }

    /**
     * @return mixed
     */
    public function getBody() {
        return $this->body;
    }

    /**
     * @return string
     */
    public function getBodyType() {
        return $this->bodyType;
    }

    /**
     * @return bool
     */
    public function hasAttachments() {
        return $this->message !== null && count($this->message->getSymfonyMessage()->getAttachments()) > 0;
    }
}
