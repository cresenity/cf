<?php

/**
 * @deprecated 1.9 perakit MIME driver SMTP lama; pesan dibangun Symfony Mime lewat CEmail_Mailer
 */
class CEmail_Driver_Smtp_Message {
    protected $body;

    protected $headers;

    protected $extraHeaders;

    protected $config;

    protected $options;

    protected $type;

    protected $uniqueid = '';

    protected $boundaries;

    protected $alt_body;

    protected $attachments = [];

    public function __construct($to, $body, $subject, CEmail_Config $config, $options) {
        $this->config = $config;
        $this->options = $options;
        $this->body = $body;
        $this->headers = [];
        $this->extraHeaders = [];
        $this->attachments = $this->prepareAttachments(carr::get($options, 'attachments', carr::get($options, 'attachment', [])));
        if (count($this->attachments) > 0 && !isset($options['type'])) {
            $this->options['type'] = 'html_attach';
        }
        if (!isset($options['encoding'])) {
            $this->options['encoding'] = '8bit';
        }

        $from = carr::get($options, 'from', $this->config->getFrom());
        $fromName = carr::get($options, 'from_name', $this->config->getFromName());
        $returnPath = carr::get($options, 'returnPath', $from);

        $this->setHeader('Date', date('r'));
        $this->setHeader('Return-Path', '<' . $returnPath . '>');
        $this->setHeader('Subject', $this->encodeMimeheader($subject));
        $this->setHeader('From', CEmail_DriverAbstract::formatAddress(['email' => $from, 'name' => $fromName]));
        $this->setHeader('To', CEmail_DriverAbstract::formatAddresses($to));

        foreach (['cc' => 'Cc', 'bcc' => 'Bcc', 'reply_to' => 'Reply-To', 'replyTo' => 'Reply-To'] as $key => $header) {
            $list = carr::get($options, $key, []);
            $list = c::collect(carr::wrap($list))->filter()->all();
            if (count($list) > 0) {
                $this->setHeader($header, CEmail_DriverAbstract::formatAddresses($list));
            }
        }

        $this->setHeader('Message-ID', '<' . $this->generateId() . '@' . $this->messageIdDomain($from) . '>');
        $this->setHeader('X-Mailer', 'Cresenity Framework');
        $this->setHeader('MIME-Version', '1.0');
        if ($this->isMultipart()) {
            $this->setBoundaries();
            $this->setHeader('Content-Type', 'multipart/mixed; boundary="' . $this->boundaries[0] . '"');
        } else {
            $this->setHeader('Content-Type', 'text/' . $this->type() . '; charset="' . $this->charset() . '"');
            $this->setHeader('Content-Transfer-Encoding', $this->encoding());
        }
    }

    /**
     * @return array struktur internal lampiran: file => [path, nama], mime, contents (base64), cid
     */
    public function getAttachments() {
        return $this->attachments;
    }

    /**
     * @return bool
     */
    protected function isMultipart() {
        return !in_array($this->type(), ['plain', 'html'], true);
    }

    /**
     * Domain untuk Message-ID: config `domain`, lalu domain alamat pengirim, lalu nama server.
     *
     * @param string $from
     *
     * @return string
     */
    protected function messageIdDomain($from) {
        $domain = $this->config->getOption('domain');
        if (!$domain && strpos((string) $from, '@') !== false) {
            $domain = substr(strrchr($from, '@'), 1);
        }

        return $domain ?: (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost.local');
    }

    /**
     * Normalisasi lampiran dari bentuk yang dipakai app (path, ['path','filename','type','disk'],
     * ['data','name','mime'], CEmail_Attachment) ke struktur yang dirakit buildMessage().
     *
     * @param mixed $attachments
     *
     * @return array
     */
    protected function prepareAttachments($attachments) {
        $prepared = [];
        foreach (carr::wrap($attachments) as $attachment) {
            $entry = null;
            if ($attachment instanceof CEmail_Attachment) {
                $entry = $attachment->attachWith(function ($path, $attachmentObject = null) {
                    return $this->attachmentFromPath($path, $attachmentObject ? $attachmentObject->as : null, $attachmentObject ? $attachmentObject->mime : null);
                }, function ($data, $attachmentObject = null) {
                    return $this->attachmentFromData($data(), $attachmentObject ? $attachmentObject->as : 'attachment', $attachmentObject ? $attachmentObject->mime : null);
                });
            } elseif ($attachment instanceof CEmail_Contract_AttachableInterface) {
                return $this->prepareAttachments($attachment->toMailAttachment());
            } elseif (is_array($attachment) && isset($attachment['contents'], $attachment['file'])) {
                $entry = $attachment;
            } elseif (is_array($attachment) && isset($attachment['data'])) {
                $entry = $this->attachmentFromData($attachment['data'], carr::get($attachment, 'name', carr::get($attachment, 'filename', 'attachment')), carr::get($attachment, 'mime', carr::get($attachment, 'type')));
            } elseif (is_array($attachment) && isset($attachment['path'])) {
                $disk = carr::get($attachment, 'disk');
                $name = carr::get($attachment, 'filename', carr::get($attachment, 'name', carr::get($attachment, 'as')));
                $mime = carr::get($attachment, 'type', carr::get($attachment, 'mime'));
                if ($disk) {
                    $entry = $this->attachmentFromData(CStorage::instance()->disk($disk)->get($attachment['path']), $name ?: basename($attachment['path']), $mime);
                } else {
                    $entry = $this->attachmentFromPath($attachment['path'], $name, $mime);
                }
            } elseif (is_string($attachment) && $attachment !== '') {
                $entry = $this->attachmentFromPath($attachment);
            }
            if ($entry) {
                $prepared[] = $entry;
            }
        }

        return $prepared;
    }

    /**
     * @param string      $path
     * @param null|string $name
     * @param null|string $mime
     *
     * @return array
     */
    protected function attachmentFromPath($path, $name = null, $mime = null) {
        if (!is_file($path)) {
            throw new CEmail_Exception_EmailSendingFailedException('Attachment file not found: ' . $path);
        }
        if (!$mime) {
            $mime = function_exists('mime_content_type') ? mime_content_type($path) : null;
        }

        return $this->attachmentFromData(file_get_contents($path), $name ?: basename($path), $mime, $path);
    }

    /**
     * @param string      $data
     * @param string      $name
     * @param null|string $mime
     * @param null|string $path
     *
     * @return array
     */
    protected function attachmentFromData($data, $name, $mime = null, $path = null) {
        return [
            'file' => [$path ?: $name, $name],
            'mime' => $mime ?: 'application/octet-stream',
            'contents' => chunk_split(base64_encode($data), 76, $this->newline()),
            'cid' => 'cid:' . md5($name . microtime(true)),
        ];
    }

    /**
     * Builds the headers and body.
     *
     * @param bool $noBcc whether to exclude Bcc headers
     *
     * @return array An array containing the headers and the body
     */
    public function buildMessage($noBcc = false) {
        $newline = $this->newline();
        $charset = $this->charset();
        $encoding = $this->encoding();

        $headers = '';
        $parts = ['Date', 'Return-Path', 'From', 'To', 'Cc', 'Bcc', 'Reply-To', 'Subject', 'Message-ID', 'X-Priority', 'X-Mailer', 'MIME-Version', 'Content-Type', 'Content-Transfer-Encoding'];
        $noBcc and array_splice($parts, 5, 1);

        foreach ($parts as $part) {
            $headers .= $this->getHeader($part);
        }

        foreach ($this->extraHeaders as $header => $value) {
            $headers .= $header . ': ' . $value . $newline;
        }

        $headers .= $newline;

        $body = '';

        if ($this->type() === 'plain' or $this->type() === 'html') {
            $body = $this->body;
        } else {
            switch ($this->type()) {
                case 'html_alt':
                    $body .= '--' . $this->boundaries[0] . $newline;
                    $body .= 'Content-Type: text/plain; charset="' . $charset . '"' . $newline;
                    $body .= 'Content-Transfer-Encoding: ' . $encoding . $newline . $newline;
                    $body .= $this->alt_body . $newline . $newline;
                    $body .= '--' . $this->boundaries[0] . $newline;
                    $body .= 'Content-Type: text/html; charset="' . $charset . '"' . $newline;
                    $body .= 'Content-Transfer-Encoding: ' . $encoding . $newline . $newline;
                    $body .= $this->body . $newline . $newline;
                    $body .= '--' . $this->boundaries[0] . '--';

                    break;
                case 'plain_attach':
                case 'html_attach':
                case 'html_inline':
                    $body .= '--' . $this->boundaries[0] . $newline;
                    $text_type = (stripos($this->type(), 'html') !== false) ? 'html' : 'plain';
                    $body .= 'Content-Type: text/' . $text_type . '; charset="' . $charset . '"' . $newline;
                    $body .= 'Content-Transfer-Encoding: ' . $encoding . $newline . $newline;
                    $body .= $this->body . $newline . $newline;
                    $attach_type = (stripos($this->type(), 'attach') !== false) ? 'attachment' : 'inline';
                    $body .= $this->getAttachmentHeaders($attach_type, $this->boundaries[0]);
                    $body .= '--' . $this->boundaries[0] . '--';

                    break;
                case 'html_alt_inline':
                    $body .= '--' . $this->boundaries[0] . $newline;
                    $body .= 'Content-Type: text/plain' . '; charset="' . $charset . '"' . $newline;
                    $body .= 'Content-Transfer-Encoding: ' . $encoding . $newline . $newline;
                    $body .= $this->alt_body . $newline . $newline;
                    $body .= '--' . $this->boundaries[0] . $newline;
                    $body .= 'Content-Type: multipart/related;' . $newline . "\tboundary=\"{$this->boundaries[1]}\"" . $newline . $newline;
                    $body .= '--' . $this->boundaries[1] . $newline;
                    $body .= 'Content-Type: text/html; charset="' . $charset . '"' . $newline;
                    $body .= 'Content-Transfer-Encoding: ' . $encoding . $newline . $newline;
                    $body .= $this->body . $newline . $newline;
                    $body .= $this->getAttachmentHeaders('inline', $this->boundaries[1]);
                    $body .= '--' . $this->boundaries[1] . '--' . $newline . $newline;
                    $body .= '--' . $this->boundaries[0] . '--';

                    break;
                case 'html_alt_attach':
                case 'html_inline_attach':
                    $body .= '--' . $this->boundaries[0] . $newline;
                    $body .= 'Content-Type: multipart/alternative;' . $newline . "\t boundary=\"{$this->boundaries[1]}\"" . $newline . $newline;
                    if (stripos($this->type(), 'alt') !== false) {
                        $body .= '--' . $this->boundaries[1] . $newline;
                        $body .= 'Content-Type: text/plain; charset="' . $charset . '"' . $newline;
                        $body .= 'Content-Transfer-Encoding: ' . $encoding . $newline . $newline;
                        $body .= $this->alt_body . $newline . $newline;
                    }
                    $body .= '--' . $this->boundaries[1] . $newline;
                    $body .= 'Content-Type: text/html; charset="' . $charset . '"' . $newline;
                    $body .= 'Content-Transfer-Encoding: ' . $encoding . $newline . $newline;
                    $body .= $this->body . $newline . $newline;
                    if (stripos($this->type(), 'inline') !== false) {
                        $body .= $this->getAttachmentHeaders('inline', $this->boundaries[1]);
                        $body .= $this->alt_body . $newline . $newline;
                    }
                    $body .= '--' . $this->boundaries[1] . '--' . $newline . $newline;
                    $body .= $this->getAttachmentHeaders('attachment', $this->boundaries[0]);
                    $body .= '--' . $this->boundaries[0] . '--';

                    break;
                case 'html_alt_inline_attach':
                    $body .= '--' . $this->boundaries[0] . $newline;
                    $body .= 'Content-Type: multipart/alternative;' . $newline . "\t boundary=\"{$this->boundaries[1]}\"" . $newline . $newline;
                    $body .= '--' . $this->boundaries[1] . $newline;
                    $body .= 'Content-Type: text/plain; charset="' . $charset . '"' . $newline;
                    $body .= 'Content-Transfer-Encoding: ' . $encoding . $newline . $newline;
                    $body .= $this->alt_body . $newline . $newline;
                    $body .= '--' . $this->boundaries[1] . $newline;
                    $body .= 'Content-Type: multipart/related;' . $newline . "\t boundary=\"{$this->boundaries[2]}\"" . $newline . $newline;
                    $body .= '--' . $this->boundaries[2] . $newline;
                    $body .= 'Content-Type: text/html; charset="' . $charset . '"' . $newline;
                    $body .= 'Content-Transfer-Encoding: ' . $encoding . $newline . $newline;
                    $body .= $this->body . $newline . $newline;
                    $body .= $this->getAttachmentHeaders('inline', $this->boundaries[2]);
                    $body .= $this->alt_body . $newline . $newline;
                    $body .= '--' . $this->boundaries[2] . '--' . $newline . $newline;
                    $body .= '--' . $this->boundaries[1] . '--' . $newline . $newline;
                    $body .= $this->getAttachmentHeaders('attachment', $this->boundaries[0]);
                    $body .= '--' . $this->boundaries[0] . '--';

                    break;
            }
        }

        return [
            'header' => $headers,
            'body' => $body,
        ];
    }

    /**
     * Gets the header.
     *
     * @param string $header    The header name. Will return all headers, if not specified
     * @param bool   $formatted Adds newline as suffix and colon as prefix, if true
     *
     * @return string|array Mail header or array of headers
     */
    protected function getHeader($header = null, $formatted = true) {
        if ($header === null) {
            return $this->headers;
        }

        if (array_key_exists($header, $this->headers)) {
            $prefix = ($formatted) ? $header . ': ' : '';
            $suffix = ($formatted) ? $this->newline() : '';

            return $prefix . $this->headers[$header] . $suffix;
        }

        return '';
    }

    /**
     * Set the boundaries to use for delimiting MIME parts.
     * If you override this, ensure you set all 3 boundaries to unique values.
     * The default boundaries include a "=_" sequence which cannot occur in quoted-printable bodies,
     * as suggested by https://www.rfc-editor.org/rfc/rfc2045#section-6.7.
     *
     * @return void
     */
    protected function setBoundaries() {
        $this->uniqueid = $this->generateId();
        $this->boundaries[0] = 'b1=_' . $this->uniqueid;
        $this->boundaries[1] = 'b2=_' . $this->uniqueid;
        $this->boundaries[2] = 'b3=_' . $this->uniqueid;
    }

    /**
     * Create a unique ID to use for boundaries.
     *
     * @return string
     */
    protected function generateId() {
        $len = 32; //32 bytes = 256 bits
        $bytes = '';
        if (function_exists('random_bytes')) {
            try {
                $bytes = random_bytes($len);
            } catch (Exception $e) {
                //Do nothing
            }
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            /** @noinspection CryptographicallySecureRandomnessInspection */
            $bytes = openssl_random_pseudo_bytes($len);
        }
        if ($bytes === '') {
            //We failed to produce a proper random string, so make do.
            //Use a hash to force the length to the same as the other methods
            $bytes = hash('sha256', uniqid((string) mt_rand(), true), true);
        }

        //We don't care about messing up base64 format here, just want a random string
        return str_replace(['=', '+', '/'], '', base64_encode(hash('sha256', $bytes, true)));
    }

    /**
     * Encodes a mimeheader.
     *
     * @param string $header Header to encode
     *
     * @return string Mimeheader encoded string
     */
    protected function encodeMimeheader($header) {
        $header = (string) $header;
        if (!preg_match('/[^\x20-\x7e]/', $header)) {
            return $header;
        }
        // determine the transfer encoding to be used
        $transferEncoding = ($this->encoding() === 'quoted-printable') ? 'Q' : 'B';

        // encode
        $header = mb_encode_mimeheader($header, $this->charset(), $transferEncoding, $this->newline());

        // and return it
        return $header;
    }

    /**
     * Get the attachment headers.
     *
     * @param mixed $type
     * @param mixed $boundary
     */
    protected function getAttachmentHeaders($type, $boundary) {
        $return = '';

        $newline = $this->newline();
        foreach ($this->attachments as $attachment) {
            $return .= '--' . $boundary . $newline;
            $return .= 'Content-Type: ' . $attachment['mime'] . '; name="' . $attachment['file'][1] . '"' . $newline;
            $return .= 'Content-Transfer-Encoding: base64' . $newline;
            $type === 'inline' and $return .= 'Content-ID: <' . substr($attachment['cid'], 4) . '>' . $newline;
            $return .= 'Content-Disposition: ' . $type . '; filename="' . $attachment['file'][1] . '"' . $newline . $newline;
            $return .= $attachment['contents'] . $newline . $newline;
        }

        return $return;
    }

    public function charset() {
        return carr::get($this->options, 'charset', 'utf-8');
    }

    public function encoding() {
        return carr::get($this->options, 'encoding', '8bit');
    }

    public function type() {
        return carr::get($this->options, 'type', 'html');
    }

    public function newline() {
        return $this->config->getOption('newline', "\r\n");
    }

    /**
     * Sets the message headers.
     *
     * @param string $header The header type
     * @param string $value  The header value
     */
    protected function setHeader($header, $value) {
        empty($value) or $this->headers[$header] = $value;

        return $this;
    }
}
