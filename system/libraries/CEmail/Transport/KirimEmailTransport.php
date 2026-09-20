<?php

use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * KirimEmail API v3 `transactional/messages` sebagai transport Symfony Mailer;
 * config `email.mailers.kirimemail` (`key`, `domain` opsional — default domain alamat pengirim).
 */
class CEmail_Transport_KirimEmailTransport extends AbstractTransport {
    const ENDPOINT = 'https://aplikasi.kirim.email/api/v3/transactional/messages';

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var null|string
     */
    protected $domain;

    /**
     * @param string      $apiKey
     * @param null|string $domain
     */
    public function __construct($apiKey, $domain = null) {
        $this->apiKey = (string) $apiKey;
        $this->domain = $domain;
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function doSend(SentMessage $message): void {
        if ($this->apiKey === '') {
            throw new TransportException('KirimEmail: api key belum diisi');
        }
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $fields = $this->fields($email);
        $domain = $this->domain ?: substr(strrchr($fields['from'], '@'), 1);

        try {
            $response = CHTTP::client()
                ->timeout(20)
                ->withHeaders(['Domain' => $domain, 'Authorization' => 'Basic ' . base64_encode('api:' . $this->apiKey)])
                ->asForm()
                ->post(static::ENDPOINT, $fields);
        } catch (Exception $e) {
            throw new TransportException('KirimEmail tidak terjangkau: ' . $e->getMessage(), 0, $e);
        }
        if (!$response->successful()) {
            throw new TransportException('KirimEmail menolak (' . $response->status() . '): ' . $response->body());
        }
        $messageId = carr::get((array) $response->json(), 'data.id', carr::get((array) $response->json(), 'id'));
        if ($messageId) {
            $message->getOriginalMessage()->getHeaders()->addTextHeader('X-Message-Id', (string) $messageId);
        }
    }

    /**
     * Field form KirimEmail dari email Symfony.
     *
     * @param Email $email
     *
     * @return array
     */
    public function fields(Email $email) {
        $from = $email->getFrom() ? $email->getFrom()[0] : null;
        if ($from === null) {
            throw new TransportException('KirimEmail: from empty');
        }
        if (count($email->getTo()) === 0) {
            throw new TransportException('KirimEmail: no recipients');
        }
        $fields = [
            'from' => $from->getAddress(),
            'to' => $this->join($email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];
        if ($from->getName() !== '') {
            $fields['from_name'] = $from->getName();
        }
        if (count($email->getCc()) > 0) {
            $fields['cc'] = $this->join($email->getCc());
        }
        if (count($email->getBcc()) > 0) {
            $fields['bcc'] = $this->join($email->getBcc());
        }
        if ($email->getHtmlBody() !== null) {
            $fields['html'] = (string) $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null) {
            $fields['text'] = (string) $email->getTextBody();
        }
        if (count($email->getReplyTo()) > 0) {
            $fields['headers'] = ['Reply-To' => $email->getReplyTo()[0]->toString()];
        }
        foreach ($email->getAttachments() as $index => $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $fields['attachments'][$index] = [
                'name' => $headers->getHeaderParameter('Content-Disposition', 'filename') ?: ($headers->getHeaderParameter('Content-Type', 'name') ?: 'attachment'),
                'type' => $attachment->getMediaType() . '/' . $attachment->getMediaSubtype(),
                'content' => base64_encode($attachment->getBody()),
            ];
        }

        return $fields;
    }

    /**
     * @param \Symfony\Component\Mime\Address[] $addresses
     *
     * @return string daftar dipisah `;` dengan nama berkutip bila ada
     */
    protected function join(array $addresses) {
        return implode(';', array_map(function ($address) {
            return $address->getName() !== '' ? $address->toString() : $address->getAddress();
        }, $addresses));
    }

    /**
     * @return string
     */
    public function __toString(): string {
        return 'kirimemail';
    }
}
