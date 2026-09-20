<?php

use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * Brevo (Sendinblue) REST v3 `smtp/email` sebagai transport Symfony Mailer; config `email.mailers.brevo`.
 */
class CEmail_Transport_BrevoTransport extends AbstractTransport {
    /**
     * @var CVendor_Brevo_TransactionalEmail
     */
    protected $client;

    /**
     * @param CVendor_Brevo_TransactionalEmail $client
     */
    public function __construct(CVendor_Brevo_TransactionalEmail $client) {
        $this->client = $client;
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function doSend(SentMessage $message): void {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        try {
            $response = $this->client->send($this->payload($email));
        } catch (CVendor_Brevo_Exception $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        $messageId = carr::get($response, 'messageId');
        if ($messageId) {
            $message->getOriginalMessage()->getHeaders()->addTextHeader('X-Message-Id', $messageId);
        }
    }

    /**
     * Payload API Brevo dari email Symfony.
     *
     * @param Email $email
     *
     * @return array
     */
    public function payload(Email $email) {
        $from = $email->getFrom() ? $email->getFrom()[0] : null;
        if ($from === null) {
            throw new TransportException('Brevo: from empty');
        }
        $payload = [
            'sender' => array_filter(['email' => $from->getAddress(), 'name' => $from->getName()]),
            'to' => $this->addresses($email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];
        if (count($payload['to']) === 0) {
            throw new TransportException('Brevo: no recipients');
        }
        foreach (['cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $key => $list) {
            if (count($list) > 0) {
                $payload[$key] = $this->addresses($list);
            }
        }
        if (count($email->getReplyTo()) > 0) {
            // Brevo hanya menerima satu replyTo
            $payload['replyTo'] = $this->addresses($email->getReplyTo())[0];
        }
        if ($email->getHtmlBody() !== null) {
            $payload['htmlContent'] = (string) $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null) {
            $payload['textContent'] = (string) $email->getTextBody();
        }
        if (!isset($payload['htmlContent']) && !isset($payload['textContent'])) {
            $payload['textContent'] = '';
        }
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $name = $headers->getHeaderParameter('Content-Disposition', 'filename') ?: ($headers->getHeaderParameter('Content-Type', 'name') ?: 'attachment');
            $attachments[] = ['name' => $name, 'content' => base64_encode($attachment->getBody())];
        }
        if (count($attachments) > 0) {
            $payload['attachment'] = $attachments;
        }
        $headers = [];
        foreach ($email->getHeaders()->all() as $header) {
            if (cstr::startsWith(strtolower($header->getName()), 'x-') && $header->getName() !== 'X-Message-Id') {
                $headers[$header->getName()] = $header->getBodyAsString();
            }
        }
        if (count($headers) > 0) {
            $payload['headers'] = $headers;
        }

        return $payload;
    }

    /**
     * @param \Symfony\Component\Mime\Address[] $addresses
     *
     * @return array
     */
    protected function addresses(array $addresses) {
        return array_map(function ($address) {
            return array_filter(['email' => $address->getAddress(), 'name' => $address->getName()]);
        }, $addresses);
    }

    /**
     * @return string
     */
    public function __toString(): string {
        return 'brevo';
    }
}
