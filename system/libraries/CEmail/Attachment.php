<?php

class CEmail_Attachment {
    use CTrait_Macroable;

    /**
     * The attached file's filename.
     *
     * @var null|string
     */
    public $as;

    /**
     * The attached file's mime type.
     *
     * @var null|string
     */
    public $mime;

    /**
     * A callback that attaches the attachment to the mail message.
     *
     * @var \Closure
     */
    protected $resolver;

    /**
     * Create a mail attachment.
     *
     * @param \Closure $resolver
     *
     * @return void
     */
    private function __construct(Closure $resolver) {
        $this->resolver = $resolver;
    }

    /**
     * Create a mail attachment from a path.
     *
     * @param string $path
     *
     * @return static
     */
    public static function fromPath($path) {
        return new static(fn ($attachment, $pathStrategy) => $pathStrategy($path, $attachment));
    }

    /**
     * Create a mail attachment from in-memory data.
     *
     * @param \Closure $data
     * @param string   $name
     *
     * @return static
     */
    public static function fromData(Closure $data, $name) {
        return (new static(
            fn ($attachment, $pathStrategy, $dataStrategy) => $dataStrategy($data, $attachment)
        ))->as($name);
    }

    /**
     * Create a mail attachment from a file in the default storage disk.
     *
     * @param string $path
     *
     * @return static
     */
    public static function fromStorage($path) {
        return static::fromStorageDisk(null, $path);
    }

    /**
     * Create a mail attachment from a file in the specified storage disk.
     *
     * @param null|string $disk
     * @param string      $path
     *
     * @return static
     */
    public static function fromStorageDisk($disk, $path) {
        return new static(function ($attachment, $pathStrategy, $dataStrategy) use ($disk, $path) {
            $storage = CStorage::instance()->disk($disk);

            $attachment
                ->as($attachment->as ?? basename($path))
                ->withMime($attachment->mime ?? $storage->mimeType($path));

            return $dataStrategy(fn () => $storage->get($path), $attachment);
        });
    }

    /**
     * Set the attached file's filename.
     *
     * @param string $name
     *
     * @return $this
     */
    /**
     * Ubah bentuk lampiran yang beredar di app menjadi daftar CEmail_Attachment:
     * path string; `['path', 'filename'|'name'|'as', 'type'|'mime', 'disk']`; `['data', 'name'|'filename', 'mime'|'type']`
     * (data string atau closure); CEmail_Attachment; CEmail_Contract_AttachableInterface; atau array campuran.
     *
     * @param mixed $attachments
     *
     * @return static[]
     */
    public static function fromLegacy($attachments) {
        $result = [];
        foreach (carr::wrap($attachments) as $attachment) {
            if ($attachment instanceof CEmail_Contract_AttachableInterface) {
                $attachment = $attachment->toMailAttachment();
            }
            if ($attachment instanceof static) {
                $result[] = $attachment;
            } elseif (is_string($attachment)) {
                if (trim($attachment) !== '') {
                    $result[] = static::fromPath($attachment);
                }
            } elseif (is_array($attachment) && array_key_exists('data', $attachment)) {
                $data = $attachment['data'];
                $mime = carr::get($attachment, 'mime', carr::get($attachment, 'type'));
                $entry = static::fromData($data instanceof Closure ? $data : function () use ($data) {
                    return $data;
                }, carr::get($attachment, 'name', carr::get($attachment, 'filename', carr::get($attachment, 'as', 'attachment'))));
                $result[] = $mime ? $entry->withMime($mime) : $entry;
            } elseif (is_array($attachment) && isset($attachment['path'])) {
                $name = carr::get($attachment, 'filename', carr::get($attachment, 'name', carr::get($attachment, 'as')));
                $mime = carr::get($attachment, 'mime', carr::get($attachment, 'type'));
                $disk = carr::get($attachment, 'disk');
                $entry = $disk ? static::fromStorageDisk($disk, $attachment['path']) : static::fromPath($attachment['path']);
                if ($name) {
                    $entry->as($name);
                }
                if ($mime) {
                    $entry->withMime($mime);
                }
                $result[] = $entry;
            } elseif (is_array($attachment) && !carr::isAssoc($attachment)) {
                $result = array_merge($result, static::fromLegacy($attachment));
            }
        }

        return $result;
    }

    public function as($name) {
        $this->as = $name;

        return $this;
    }

    /**
     * Set the attached file's mime type.
     *
     * @param string $mime
     *
     * @return $this
     */
    public function withMime($mime) {
        $this->mime = $mime;

        return $this;
    }

    /**
     * Attach the attachment with the given strategies.
     *
     * @param \Closure $pathStrategy
     * @param \Closure $dataStrategy
     *
     * @return mixed
     */
    public function attachWith(Closure $pathStrategy, Closure $dataStrategy) {
        return ($this->resolver)($this, $pathStrategy, $dataStrategy);
    }

    /**
     * Attach the attachment to a built-in mail type.
     *
     * @param \Illuminate\Mail\Mailable|\Illuminate\Mail\Message|\Illuminate\Notifications\Messages\MailMessage $mail
     *
     * @return mixed
     */
    public function attachTo($mail) {
        return $this->attachWith(
            fn ($path) => $mail->attach($path, ['as' => $this->as, 'mime' => $this->mime]),
            fn ($data) => $mail->attachData($data(), $this->as, ['mime' => $this->mime])
        );
    }
}
