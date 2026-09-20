<?php

use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * MailerSend REST `/v1/email` sebagai transport Symfony Mailer; config `email.mailers.mailersend` (`key`).
 */
class CEmail_Transport_MailersendTransport extends AbstractTransport {
    /**
     * @var CVendor_MailerSend
     */
    protected $client;

    /**
     * @param CVendor_MailerSend $client
     */
    public function __construct(CVendor_MailerSend $client) {
        $this->client = $client;
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function doSend(SentMessage $message): void {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        try {
            $response = $this->client->email->send($this->params($email));
        } catch (Exception $e) {
            $status = method_exists($e, 'getStatusCode') ? ' (' . $e->getStatusCode() . ')' : '';

            throw new TransportException('MailerSend menolak' . $status . ': ' . $e->getMessage(), 0, $e);
        }
        $statusCode = (int) carr::get($response, 'status_code');
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new TransportException('MailerSend menolak (' . $statusCode . '): ' . json_encode(carr::get($response, 'body')));
        }
        $messageId = carr::get($response, 'headers.X-Message-Id.0', carr::get($response, 'headers.x-message-id.0'));
        if ($messageId) {
            $message->getOriginalMessage()->getHeaders()->addTextHeader('X-Message-Id', $messageId);
        }
    }

    /**
     * EmailParams MailerSend dari email Symfony.
     *
     * @param Email $email
     *
     * @return CVendor_MailerSend_Helpers_Builder_EmailParams
     */
    public function params(Email $email) {
        $from = $email->getFrom() ? $email->getFrom()[0] : null;
        if ($from === null) {
            throw new TransportException('MailerSend: from empty');
        }
        if (count($email->getTo()) === 0) {
            throw new TransportException('MailerSend: no recipients');
        }
        $params = (new CVendor_MailerSend_Helpers_Builder_EmailParams())
            ->setFrom($from->getAddress())
            ->setFromName($from->getName() !== '' ? $from->getName() : $from->getAddress())
            ->setRecipients($this->recipients($email->getTo()))
            ->setSubject((string) $email->getSubject());
        if (count($email->getCc()) > 0) {
            $params->setCc($this->recipients($email->getCc()));
        }
        if (count($email->getBcc()) > 0) {
            $params->setBcc($this->recipients($email->getBcc()));
        }
        if (count($email->getReplyTo()) > 0) {
            $replyTo = $email->getReplyTo()[0];
            $params->setReplyTo($replyTo->getAddress());
            if ($replyTo->getName() !== '') {
                $params->setReplyToName($replyTo->getName());
            }
        }
        if ($email->getHtmlBody() !== null) {
            $params->setHtml((string) $email->getHtmlBody());
        }
        if ($email->getTextBody() !== null) {
            $params->setText((string) $email->getTextBody());
        }
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $name = $headers->getHeaderParameter('Content-Disposition', 'filename') ?: ($headers->getHeaderParameter('Content-Type', 'name') ?: 'attachment');
            $attachments[] = new CVendor_MailerSend_Helpers_Builder_Attachment(base64_encode($attachment->getBody()), $name, 'attachment');
        }
        if (count($attachments) > 0) {
            $params->setAttachments($attachments);
        }

        return $params;
    }

    /**
     * @param \Symfony\Component\Mime\Address[] $addresses
     *
     * @return CVendor_MailerSend_Helpers_Builder_Recipient[]
     */
    protected function recipients(array $addresses) {
        return array_map(function ($address) {
            return new CVendor_MailerSend_Helpers_Builder_Recipient($address->getAddress(), $address->getName() !== '' ? $address->getName() : null);
        }, $addresses);
    }

    /**
     * @return string
     */
    public function __toString(): string {
        return 'mailersend';
    }
}
