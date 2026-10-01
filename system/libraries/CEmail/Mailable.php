<?php

use Symfony\Component\Mime\Address;
use PHPUnit\Framework\Assert as PHPUnit;
use Illuminate\Contracts\Support\Renderable;

use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Header\MetadataHeader;

class CEmail_Mailable implements CEmail_Contract_MailableInterface, Renderable {
    use CTrait_Conditionable, CTrait_ForwardsCalls, CTrait_Localizable, CTrait_Macroable {
        __call as macroCall;
    }

    /**
     * The locale of the message.
     *
     * @var string
     */
    public $locale;

    /**
     * The person the message is from.
     *
     * @var array
     */
    public $from = [];

    /**
     * The "to" recipients of the message.
     *
     * @var array
     */
    public $to = [];

    /**
     * The "cc" recipients of the message.
     *
     * @var array
     */
    public $cc = [];

    /**
     * The "bcc" recipients of the message.
     *
     * @var array
     */
    public $bcc = [];

    /**
     * The "reply to" recipients of the message.
     *
     * @var array
     */
    public $replyTo = [];

    /**
     * The subject of the message.
     *
     * @var string
     */
    public $subject;

    /**
     * The Markdown template for the message (if applicable).
     *
     * @var string
     */
    public $markdown;

    /**
     * The view to use for the message.
     *
     * @var string
     */
    public $view;

    /**
     * The plain text view to use for the message.
     *
     * @var string
     */
    public $textView;

    /**
     * The view data for the message.
     *
     * @var array
     */
    public $viewData = [];

    /**
     * The attachments for the message.
     *
     * @var array
     */
    public $attachments = [];

    /**
     * The raw attachments for the message.
     *
     * @var array
     */
    public $rawAttachments = [];

    /**
     * The attachments from a storage disk.
     *
     * @var array
     */
    public $diskAttachments = [];

    /**
     * The callbacks for the message.
     *
     * @var array
     */
    public $callbacks = [];

    /**
     * The name of the theme that should be used when formatting the message.
     *
     * @var null|string
     */
    public $theme;

    /**
     * The name of the mailer that should send the message.
     *
     * @var string
     */
    public $mailer;

    /**
     * The callback that should be invoked while building the view data.
     *
     * @var callable
     */
    public static $viewDataCallback;

    /**
     * The HTML to use for the message.
     *
     * @var string
     */
    protected $html;

    /**
     * The tags for the message.
     *
     * @var array
     */
    protected $tags = [];

    /**
     * The metadata for the message.
     *
     * @var array
     */
    protected $metadata = [];

    /**
     * The rendered mailable views for testing / assertions.
     *
     * @var array
     */
    protected $assertionableRenderStrings;

    /**
     * @var int
     */
    protected $delay = 0;

    /**
     * @var int
     */
    protected $connection = null;

    /**
     * Apakah envelope()/content()/headers()/attachments() sudah dipindahkan ke properti mailable.
     *
     * @var bool
     */
    protected $definitionsHydrated = false;

    /**
     * Jalankan build() bila ada, lalu pindahkan envelope()/content()/headers()/attachments() ke mailable.
     *
     * @throws \ReflectionException
     *
     * @return void
     */
    protected function prepareMailableForDelivery() {
        if (method_exists($this, 'build')) {
            CContainer::getInstance()->call([$this, 'build']);
        }

        $this->hydrateDefinitions();
    }

    /**
     * Sekali per instance, aman dipanggil berulang (assertion, render, send).
     *
     * @return void
     */
    protected function hydrateDefinitions() {
        if ($this->definitionsHydrated) {
            return;
        }
        $this->definitionsHydrated = true;

        $this->ensureHeadersAreHydrated();
        $this->ensureEnvelopeIsHydrated();
        $this->ensureContentIsHydrated();
        $this->ensureAttachmentsAreHydrated();
    }

    /**
     * @return void
     */
    protected function ensureHeadersAreHydrated() {
        if (!method_exists($this, 'headers')) {
            return;
        }
        $headers = $this->headers();
        if (!$headers instanceof CEmail_Mailable_Headers) {
            return;
        }

        $this->withSymfonyMessage(function ($message) use ($headers) {
            $symfonyHeaders = $message->getHeaders();

            if (strlen((string) $headers->messageId) > 0) {
                $symfonyHeaders->remove('Message-ID');
                $symfonyHeaders->addIdHeader('Message-ID', trim($headers->messageId, '<>'));
            }
            if (count($headers->references) > 0) {
                $symfonyHeaders->remove('References');
                $symfonyHeaders->addIdHeader('References', array_map(function ($id) {
                    return trim((string) $id, '<>');
                }, $headers->references));
            }
            foreach ($headers->text as $name => $value) {
                $symfonyHeaders->addTextHeader($name, $value);
            }
        });
    }

    /**
     * @return void
     */
    protected function ensureEnvelopeIsHydrated() {
        if (!method_exists($this, 'envelope')) {
            return;
        }
        $envelope = $this->envelope();
        if (!$envelope instanceof CEmail_Mailable_Envelope) {
            return;
        }

        if ($envelope->from !== null) {
            $this->from($envelope->from->address, $envelope->from->name);
        }
        foreach (['to', 'cc', 'bcc', 'replyTo'] as $type) {
            foreach ($envelope->{$type} as $address) {
                $this->{$type}($address->address, $address->name);
            }
        }
        if (strlen((string) $envelope->subject) > 0) {
            $this->subject($envelope->subject);
        }
        foreach ($envelope->tags as $tag) {
            $this->tag($tag);
        }
        foreach ($envelope->metadata as $key => $value) {
            $this->metadata($key, $value);
        }
        foreach ($envelope->using as $callback) {
            $this->withSymfonyMessage($callback);
        }
    }

    /**
     * @return void
     */
    protected function ensureContentIsHydrated() {
        if (!method_exists($this, 'content')) {
            return;
        }
        $content = $this->content();
        if (!$content instanceof CEmail_Mailable_Content) {
            return;
        }

        if ($content->view) {
            $this->view($content->view);
        }
        if ($content->html) {
            $this->view($content->html);
        }
        if ($content->text) {
            $this->text($content->text);
        }
        if ($content->markdown) {
            $this->markdown($content->markdown);
        }
        if ($content->htmlString) {
            $this->html($content->htmlString);
        }
        foreach ($content->with as $key => $value) {
            $this->with($key, $value);
        }
    }

    /**
     * @return void
     */
    protected function ensureAttachmentsAreHydrated() {
        if (!method_exists($this, 'attachments')) {
            return;
        }
        $attachments = $this->attachments();
        if (!is_array($attachments) && !$attachments instanceof Traversable) {
            return;
        }

        foreach ($attachments as $file => $options) {
            if (is_int($file)) {
                $this->attach($options);
            } else {
                $this->attach($file, (array) $options);
            }
        }
    }

    /**
     * Lampirkan banyak berkas: daftar path/Attachment, atau path => opsi.
     *
     * @param iterable $files
     *
     * @return $this
     */
    public function attachMany($files) {
        foreach ($files as $file => $options) {
            if (is_int($file)) {
                $this->attach($options);
            } else {
                $this->attach($file, (array) $options);
            }
        }

        return $this;
    }

    /**
     * Send the message using the given mailer.
     *
     * @param \CEmail_Contract_FactoryInterface|\Illuminate\Contracts\Mail\Mailer $mailer
     *
     * @return null|\CEmail_SentMessage
     */
    public function send($mailer) {
        return $this->withLocale($this->locale, function () use ($mailer) {
            $this->prepareMailableForDelivery();

            $mailer = $mailer instanceof CEmail_Contract_FactoryInterface
                            ? $mailer->mailer($this->mailer)
                            : $mailer;

            return $mailer->send($this->buildView(), $this->buildViewData(), function ($message) {
                $this->buildFrom($message)
                    ->buildRecipients($message)
                    ->buildSubject($message)
                    ->buildTags($message)
                    ->buildMetadata($message)
                    ->runCallbacks($message)
                    ->buildAttachments($message);
            });
        });
    }

    /**
     * Queue the message for sending.
     *
     * @param \CQueue_FactoryInterface $queue
     *
     * @return mixed
     */
    public function queue(CQueue_FactoryInterface $queue) {
        if (isset($this->delay) && $this->delay > 0) {
            return $this->later($this->delay, $queue);
        }

        $connection = property_exists($this, 'connection') ? $this->connection : null;

        $queueName = property_exists($this, 'queue') ? $this->queue : null;

        return $queue->connection($connection)->pushOn(
            $queueName ?: null,
            $this->newQueuedJob()
        );
    }

    /**
     * Deliver the queued message after (n) seconds.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param \CQueue_FactoryInterface             $queue
     *
     * @return mixed
     */
    public function later($delay, CQueue_FactoryInterface $queue) {
        $connection = property_exists($this, 'connection') ? $this->connection : null;

        $queueName = property_exists($this, 'queue') ? $this->queue : null;

        return $queue->connection($connection)->laterOn(
            $queueName ?: null,
            $delay,
            $this->newQueuedJob()
        );
    }

    /**
     * Make the queued mailable job instance.
     *
     * @return mixed
     */
    protected function newQueuedJob() {
        return (new CEmail_TaskQueue_SendQueuedMailable($this))
            ->through(array_merge(
                method_exists($this, 'middleware') ? $this->middleware() : [],
                $this->middleware ?? []
            ));
    }

    /**
     * Render the mailable into a view.
     *
     * @throws \ReflectionException
     *
     * @return string
     */
    public function render() {
        return $this->withLocale($this->locale, function () {
            $this->prepareMailableForDelivery();

            return CEmail::mailer()->render(
                $this->buildView(),
                $this->buildViewData()
            );
        });
    }

    /**
     * Build the view for the message.
     *
     * @throws \ReflectionException
     *
     * @return array|string
     */
    protected function buildView() {
        if (isset($this->html)) {
            return array_filter([
                'html' => new CBase_HtmlString($this->html),
                'text' => $this->textView ?? null,
            ]);
        }

        if (isset($this->markdown)) {
            return $this->buildMarkdownView();
        }

        if (isset($this->view, $this->textView)) {
            return [$this->view, $this->textView];
        } elseif (isset($this->textView)) {
            return ['text' => $this->textView];
        }

        return $this->view;
    }

    /**
     * Build the Markdown view for the message.
     *
     * @throws \ReflectionException
     *
     * @return array
     */
    protected function buildMarkdownView() {
        $markdown = CContainer::getInstance()->make(CEmail_Markdown::class);

        if (isset($this->theme)) {
            $markdown->theme($this->theme);
        }

        $data = $this->buildViewData();

        return [
            'html' => $markdown->render($this->markdown, $data),
            'text' => $this->buildMarkdownText($markdown, $data),
        ];
    }

    /**
     * Build the view data for the message.
     *
     * @throws \ReflectionException
     *
     * @return array
     */
    public function buildViewData() {
        $data = $this->viewData;

        if (static::$viewDataCallback) {
            $data = array_merge($data, call_user_func(static::$viewDataCallback, $this));
        }

        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== self::class) {
                $data[$property->getName()] = $property->getValue($this);
            }
        }

        return $data;
    }

    /**
     * Build the text view for a Markdown message.
     *
     * @param \Illuminate\Mail\Markdown $markdown
     * @param array                     $data
     *
     * @return string
     */
    protected function buildMarkdownText($markdown, $data) {
        return $this->textView
                ?? $markdown->renderText($this->markdown, $data);
    }

    /**
     * Add the sender to the message.
     *
     * @param \CEmail_Message $message
     *
     * @return $this
     */
    protected function buildFrom($message) {
        if (!empty($this->from)) {
            $message->from($this->from[0]['address'], $this->from[0]['name']);
        }

        return $this;
    }

    /**
     * Add all of the recipients to the message.
     *
     * @param \CEmail_Message $message
     *
     * @return $this
     */
    protected function buildRecipients($message) {
        foreach (['to', 'cc', 'bcc', 'replyTo'] as $type) {
            foreach ($this->{$type} as $recipient) {
                $message->{$type}($recipient['address'], $recipient['name']);
            }
        }

        return $this;
    }

    /**
     * Set the subject for the message.
     *
     * @param \CEmail_Message $message
     *
     * @return $this
     */
    protected function buildSubject($message) {
        if ($this->subject) {
            $message->subject($this->subject);
        } else {
            $message->subject(cstr::title(cstr::snake(c::classBasename($this), ' ')));
        }

        return $this;
    }

    /**
     * Add all of the attachments to the message.
     *
     * @param \CEmail_Message $message
     *
     * @return $this
     */
    protected function buildAttachments($message) {
        foreach ($this->attachments as $attachment) {
            $message->attach($attachment['file'], $attachment['options']);
        }

        foreach ($this->rawAttachments as $attachment) {
            $message->attachData(
                $attachment['data'],
                $attachment['name'],
                $attachment['options']
            );
        }

        $this->buildDiskAttachments($message);

        return $this;
    }

    /**
     * Add all of the disk attachments to the message.
     *
     * @param \CEmail_Message $message
     *
     * @return void
     */
    protected function buildDiskAttachments($message) {
        foreach ($this->diskAttachments as $attachment) {
            $storage = CStorage::instance()->disk($attachment['disk']);

            $message->attachData(
                $storage->get($attachment['path']),
                $attachment['name'] ?? basename($attachment['path']),
                array_merge(['mime' => $storage->mimeType($attachment['path'])], $attachment['options'])
            );
        }
    }

    /**
     * Add all defined tags to the message.
     *
     * @param \CEmail_Message $message
     *
     * @return $this
     */
    protected function buildTags($message) {
        if ($this->tags) {
            foreach ($this->tags as $tag) {
                $message->getHeaders()->add(new TagHeader($tag));
            }
        }

        return $this;
    }

    /**
     * Add all defined metadata to the message.
     *
     * @param \CEmail_Message $message
     *
     * @return $this
     */
    protected function buildMetadata($message) {
        if ($this->metadata) {
            foreach ($this->metadata as $key => $value) {
                $message->getHeaders()->add(new MetadataHeader($key, $value));
            }
        }

        return $this;
    }

    /**
     * Run the callbacks for the message.
     *
     * @param \CEmail_Message $message
     *
     * @return $this
     */
    protected function runCallbacks($message) {
        foreach ($this->callbacks as $callback) {
            $callback($message->getSymfonyMessage());
        }

        return $this;
    }

    /**
     * Set the locale of the message.
     *
     * @param string $locale
     *
     * @return $this
     */
    public function locale($locale) {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Set the priority of this message.
     *
     * The value is an integer where 1 is the highest priority and 5 is the lowest.
     *
     * @param int $level
     *
     * @return $this
     */
    public function priority($level = 3) {
        $this->callbacks[] = function ($message) use ($level) {
            $message->priority($level);
        };

        return $this;
    }

    /**
     * Set the sender of the message.
     *
     * @param object|array|string $address
     * @param null|string         $name
     *
     * @return $this
     */
    public function from($address, $name = null) {
        return $this->setAddress($address, $name, 'from');
    }

    /**
     * Determine if the given recipient is set on the mailable.
     *
     * @param object|array|string $address
     * @param null|string         $name
     *
     * @return bool
     */
    public function hasFrom($address, $name = null) {
        return $this->hasRecipient($address, $name, 'from');
    }

    /**
     * Set the recipients of the message.
     *
     * @param object|array|string $address
     * @param null|string         $name
     *
     * @return $this
     */
    public function to($address, $name = null) {
        return $this->setAddress($address, $name, 'to');
    }

    /**
     * Determine if the given recipient is set on the mailable.
     *
     * @param object|array|string $address
     * @param null|string         $name
     *
     * @return bool
     */
    public function hasTo($address, $name = null) {
        return $this->hasRecipient($address, $name, 'to');
    }

    /**
     * Set the recipients of the message.
     *
     * @param object|array|string $address
     * @param null|string         $name
     *
     * @return $this
     */
    public function cc($address, $name = null) {
        return $this->setAddress($address, $name, 'cc');
    }

    /**
     * Determine if the given recipient is set on the mailable.
     *
     * @param object|array|string $address
     * @param null|string         $name
     *
     * @return bool
     */
    public function hasCc($address, $name = null) {
        return $this->hasRecipient($address, $name, 'cc');
    }

    /**
     * Set the recipients of the message.
     *
     * @param object|array|string $address
     * @param null|string         $name
     *
     * @return $this
     */
    public function bcc($address, $name = null) {
        return $this->setAddress($address, $name, 'bcc');
    }

    /**
     * Determine if the given recipient is set on the mailable.
     *
     * @param object|array|string $address
     * @param null|string         $name
     *
     * @return bool
     */
    public function hasBcc($address, $name = null) {
        return $this->hasRecipient($address, $name, 'bcc');
    }

    /**
     * Set the "reply to" address of the message.
     *
     * @param object|array|string $address
     * @param null|string         $name
     *
     * @return $this
     */
    public function replyTo($address, $name = null) {
        return $this->setAddress($address, $name, 'replyTo');
    }

    /**
     * Determine if the given replyTo is set on the mailable.
     *
     * @param object|array|string $address
     * @param null|string         $name
     *
     * @return bool
     */
    public function hasReplyTo($address, $name = null) {
        return $this->hasRecipient($address, $name, 'replyTo');
    }

    /**
     * Set the recipients of the message.
     *
     * All recipients are stored internally as [['name' => ?, 'address' => ?]]
     *
     * @param object|array|string $address
     * @param null|string         $name
     * @param string              $property
     *
     * @return $this
     */
    protected function setAddress($address, $name = null, $property = 'to') {
        if (empty($address)) {
            return $this;
        }

        foreach ($this->addressesToArray($address, $name) as $recipient) {
            $recipient = $this->normalizeRecipient($recipient);

            $this->{$property}[] = [
                'name' => $recipient->name ?? null,
                'address' => $recipient->email,
            ];
        }

        // alamat yang sama hanya sekali; yang terakhir ditambahkan yang menang (nama terbaru)
        $this->{$property} = c::collect($this->{$property})
            ->reverse()
            ->unique('address')
            ->reverse()
            ->values()
            ->all();

        return $this;
    }

    /**
     * Convert the given recipient arguments to an array.
     *
     * @param object|array|string $address
     * @param null|string         $name
     *
     * @return array
     */
    protected function addressesToArray($address, $name) {
        if (!is_array($address) && !$address instanceof CCollection) {
            $address = is_string($name) ? [['name' => $name, 'email' => $address]] : [$address];
        }

        return $address;
    }

    /**
     * Convert the given recipient into an object.
     *
     * @param mixed $recipient
     *
     * @return object
     */
    protected function normalizeRecipient($recipient) {
        if (is_array($recipient)) {
            if (array_values($recipient) === $recipient) {
                return (object) array_map(function ($email) {
                    return compact('email');
                }, $recipient);
            }

            return (object) $recipient;
        } elseif (is_string($recipient)) {
            return (object) ['email' => $recipient];
        } elseif ($recipient instanceof Address) {
            return (object) ['email' => $recipient->getAddress(), 'name' => $recipient->getName()];
        }

        return $recipient;
    }

    /**
     * Determine if the given recipient is set on the mailable.
     *
     * @param object|array|string $address
     * @param null|string         $name
     * @param string              $property
     *
     * @return bool
     */
    protected function hasRecipient($address, $name = null, $property = 'to') {
        $this->hydrateDefinitions();

        if (empty($address)) {
            return false;
        }

        $expected = $this->normalizeRecipient(
            $this->addressesToArray($address, $name)[0]
        );

        $expected = [
            'name' => $expected->name ?? null,
            'address' => $expected->email,
        ];

        return c::collect($this->{$property})->contains(function ($actual) use ($expected) {
            if (!isset($expected['name'])) {
                return $actual['address'] == $expected['address'];
            }

            return $actual == $expected;
        });
    }

    /**
     * Set the subject of the message.
     *
     * @param string $subject
     *
     * @return $this
     */
    public function subject($subject) {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Determine if the mailable has the given subject.
     *
     * @param string $subject
     *
     * @return bool
     */
    public function hasSubject($subject) {
        $this->hydrateDefinitions();

        return $this->subject === $subject;
    }

    /**
     * Set the Markdown template for the message.
     *
     * @param string $view
     * @param array  $data
     *
     * @return $this
     */
    public function markdown($view, array $data = []) {
        $this->markdown = $view;
        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    /**
     * Set the view and view data for the message.
     *
     * @param string $view
     * @param array  $data
     *
     * @return $this
     */
    public function view($view, array $data = []) {
        $this->view = $view;
        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    /**
     * Set the rendered HTML content for the message.
     *
     * @param string $html
     *
     * @return $this
     */
    public function html($html) {
        $this->html = $html;

        return $this;
    }

    /**
     * Set the plain text view for the message.
     *
     * @param string $textView
     * @param array  $data
     *
     * @return $this
     */
    public function text($textView, array $data = []) {
        $this->textView = $textView;
        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    /**
     * Set the view data for the message.
     *
     * @param string|array $key
     * @param mixed        $value
     *
     * @return $this
     */
    public function with($key, $value = null) {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    /**
     * Attach a file to the message.
     *
     * @param string|\CEmail_Contract_AttachableInterface|\CEmail_Attachment $file
     * @param array                                                          $options
     *
     * @return $this
     */
    public function attach($file, array $options = []) {
        if ($file instanceof CEmail_Contract_AttachableInterface) {
            $file = $file->toMailAttachment();
        }

        if ($file instanceof CEmail_Attachment) {
            return $file->attachTo($this);
        }

        $this->attachments = c::collect($this->attachments)
            ->push(compact('file', 'options'))
            ->unique('file')
            ->all();

        return $this;
    }

    /**
     * Attach a file to the message from storage.
     *
     * @param string      $path
     * @param null|string $name
     * @param array       $options
     *
     * @return $this
     */
    public function attachFromStorage($path, $name = null, array $options = []) {
        return $this->attachFromStorageDisk(null, $path, $name, $options);
    }

    /**
     * Attach a file to the message from storage.
     *
     * @param string      $disk
     * @param string      $path
     * @param null|string $name
     * @param array       $options
     *
     * @return $this
     */
    public function attachFromStorageDisk($disk, $path, $name = null, array $options = []) {
        $this->diskAttachments = c::collect($this->diskAttachments)->push([
            'disk' => $disk,
            'path' => $path,
            'name' => $name ?? basename($path),
            'options' => $options,
        ])->unique(function ($file) {
            return $file['name'] . $file['disk'] . $file['path'];
        })->all();

        return $this;
    }

    /**
     * Attach in-memory data as an attachment.
     *
     * @param string $data
     * @param string $name
     * @param array  $options
     *
     * @return $this
     */
    public function attachData($data, $name, array $options = []) {
        $this->rawAttachments = c::collect($this->rawAttachments)
            ->push(compact('data', 'name', 'options'))
            ->unique(function ($file) {
                return $file['name'] . $file['data'];
            })->all();

        return $this;
    }

    /**
     * Add a tag header to the message when supported by the underlying transport.
     *
     * @param string $value
     *
     * @return $this
     */
    public function tag($value) {
        array_push($this->tags, $value);

        return $this;
    }

    /**
     * Add a metadata header to the message when supported by the underlying transport.
     *
     * @param string $key
     * @param string $value
     *
     * @return $this
     */
    public function metadata($key, $value) {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Assert that the given text is present in the HTML email body.
     *
     * @param string $string
     *
     * @return $this
     */
    public function assertSeeInHtml($string) {
        list($html, $text) = $this->renderForAssertions();

        PHPUnit::assertTrue(
            str_contains($html, $string),
            "Did not see expected text [{$string}] within email body."
        );

        return $this;
    }

    /**
     * Assert that the given text is not present in the HTML email body.
     *
     * @param string $string
     *
     * @return $this
     */
    public function assertDontSeeInHtml($string) {
        list($html, $text) = $this->renderForAssertions();

        PHPUnit::assertFalse(
            str_contains($html, $string),
            "Saw unexpected text [{$string}] within email body."
        );

        return $this;
    }

    /**
     * Assert that the given text strings are present in order in the HTML email body.
     *
     * @param array $strings
     *
     * @return $this
     */
    public function assertSeeInOrderInHtml($strings) {
        list($html, $text) = $this->renderForAssertions();

        PHPUnit::assertThat($strings, new CTesting_Constraint_SeeInOrder($html));

        return $this;
    }

    /**
     * Assert that the given text is present in the plain-text email body.
     *
     * @param string $string
     *
     * @return $this
     */
    public function assertSeeInText($string) {
        list($html, $text) = $this->renderForAssertions();

        PHPUnit::assertTrue(
            str_contains($text, $string),
            "Did not see expected text [{$string}] within text email body."
        );

        return $this;
    }

    /**
     * Assert that the given text is not present in the plain-text email body.
     *
     * @param string $string
     *
     * @return $this
     */
    public function assertDontSeeInText($string) {
        list($html, $text) = $this->renderForAssertions();

        PHPUnit::assertFalse(
            str_contains($text, $string),
            "Saw unexpected text [{$string}] within text email body."
        );

        return $this;
    }

    /**
     * Assert that the given text strings are present in order in the plain-text email body.
     *
     * @param array $strings
     *
     * @return $this
     */
    public function assertSeeInOrderInText($strings) {
        list($html, $text) = $this->renderForAssertions();

        PHPUnit::assertThat($strings, new CTesting_Constraint_SeeInOrder($text));

        return $this;
    }

    /**
     * Render the HTML and plain-text version of the mailable into views for assertions.
     *
     * @throws \ReflectionException
     *
     * @return array
     */
    protected function renderForAssertions() {
        if ($this->assertionableRenderStrings) {
            return $this->assertionableRenderStrings;
        }

        return $this->assertionableRenderStrings = $this->withLocale($this->locale, function () {
            $this->prepareMailableForDelivery();

            $html = CEmail::mailer()->render(
                $view = $this->buildView(),
                $this->buildViewData()
            );

            if (is_array($view) && isset($view[1])) {
                $text = $view[1];
            }

            $text ??= $view['text'] ?? '';

            if (!empty($text) && !$text instanceof CInterface_Htmlable) {
                $text = CEmail::mailer()->render(
                    $text,
                    $this->buildViewData()
                );
            }

            return [(string) $html, (string) $text];
        });
    }

    /**
     * @param string $tag
     *
     * @return bool
     */
    public function hasTag($tag) {
        $this->hydrateDefinitions();

        return in_array($tag, $this->tags, true);
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    public function hasMetadata($key, $value) {
        $this->hydrateDefinitions();

        return isset($this->metadata[$key]) && $this->metadata[$key] === $value;
    }

    /**
     * @param CEmail_Attachment|CEmail_Contract_AttachableInterface|string $file
     * @param array                                                        $options
     *
     * @return bool
     */
    public function hasAttachment($file, array $options = []) {
        $this->hydrateDefinitions();

        if ($file instanceof CEmail_Contract_AttachableInterface) {
            $file = $file->toMailAttachment();
        }

        if ($file instanceof CEmail_Attachment) {
            return (bool) $file->attachWith(
                function ($path) use ($file) {
                    return $this->hasAttachment($path, ['as' => $file->as, 'mime' => $file->mime]);
                },
                function ($data) use ($file) {
                    return $this->hasAttachedData($data(), $file->as, ['mime' => $file->mime]);
                }
            );
        }

        return c::collect($this->attachments)->contains(function ($attachment) use ($file, $options) {
            return $attachment['file'] === $file
                && array_filter($attachment['options']) === array_filter($options);
        });
    }

    /**
     * @param string $data
     * @param string $name
     * @param array  $options
     *
     * @return bool
     */
    public function hasAttachedData($data, $name, array $options = []) {
        $this->hydrateDefinitions();

        return c::collect($this->rawAttachments)->contains(function ($attachment) use ($data, $name, $options) {
            return $attachment['data'] === $data
                && $attachment['name'] === $name
                && array_filter($attachment['options']) === array_filter($options);
        });
    }

    /**
     * @param string      $path
     * @param null|string $name
     * @param array       $options
     *
     * @return bool
     */
    public function hasAttachmentFromStorage($path, $name = null, array $options = []) {
        return $this->hasAttachmentFromStorageDisk(null, $path, $name, $options);
    }

    /**
     * @param null|string $disk
     * @param string      $path
     * @param null|string $name
     * @param array       $options
     *
     * @return bool
     */
    public function hasAttachmentFromStorageDisk($disk, $path, $name = null, array $options = []) {
        $this->hydrateDefinitions();

        return c::collect($this->diskAttachments)->contains(function ($attachment) use ($disk, $path, $name, $options) {
            return $attachment['disk'] === $disk
                && $attachment['path'] === $path
                && $attachment['name'] === ($name ?? basename($path))
                && array_filter($attachment['options']) === array_filter($options);
        });
    }

    /**
     * @param mixed       $address
     * @param null|string $name
     *
     * @return string
     */
    protected function formatAssertionRecipient($address, $name = null) {
        if (!is_string($address)) {
            return 'unknown';
        }

        return is_string($name) ? $name . ' <' . $address . '>' : $address;
    }

    /**
     * @param string      $address
     * @param null|string $name
     *
     * @return $this
     */
    public function assertFrom($address, $name = null) {
        PHPUnit::assertTrue(
            $this->hasFrom($address, $name),
            'Email was not from expected address [' . $this->formatAssertionRecipient($address, $name) . '].'
        );

        return $this;
    }

    /**
     * @param string      $address
     * @param null|string $name
     *
     * @return $this
     */
    public function assertHasTo($address, $name = null) {
        PHPUnit::assertTrue(
            $this->hasTo($address, $name),
            'Did not see expected recipient [' . $this->formatAssertionRecipient($address, $name) . "] in email 'to' recipients."
        );

        return $this;
    }

    /**
     * @param string      $address
     * @param null|string $name
     *
     * @return $this
     */
    public function assertHasCc($address, $name = null) {
        PHPUnit::assertTrue(
            $this->hasCc($address, $name),
            'Did not see expected recipient [' . $this->formatAssertionRecipient($address, $name) . "] in email 'cc' recipients."
        );

        return $this;
    }

    /**
     * @param string      $address
     * @param null|string $name
     *
     * @return $this
     */
    public function assertHasBcc($address, $name = null) {
        PHPUnit::assertTrue(
            $this->hasBcc($address, $name),
            'Did not see expected recipient [' . $this->formatAssertionRecipient($address, $name) . "] in email 'bcc' recipients."
        );

        return $this;
    }

    /**
     * @param string      $address
     * @param null|string $name
     *
     * @return $this
     */
    public function assertHasReplyTo($address, $name = null) {
        PHPUnit::assertTrue(
            $this->hasReplyTo($address, $name),
            'Did not see expected address [' . $this->formatAssertionRecipient($address, $name) . '] as email reply-to.'
        );

        return $this;
    }

    /**
     * @param string $subject
     *
     * @return $this
     */
    public function assertHasSubject($subject) {
        PHPUnit::assertTrue(
            $this->hasSubject($subject),
            "Did not see expected subject [{$subject}] in email subject."
        );

        return $this;
    }

    /**
     * @param string $tag
     *
     * @return $this
     */
    public function assertHasTag($tag) {
        PHPUnit::assertTrue(
            $this->hasTag($tag),
            "Did not see expected tag [{$tag}] in email tags."
        );

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function assertHasMetadata($key, $value) {
        PHPUnit::assertTrue(
            $this->hasMetadata($key, $value),
            "Did not see expected key [{$key}] and value [" . (is_scalar($value) ? $value : gettype($value)) . '] in email metadata.'
        );

        return $this;
    }

    /**
     * @param CEmail_Attachment|CEmail_Contract_AttachableInterface|string $file
     * @param array                                                        $options
     *
     * @return $this
     */
    public function assertHasAttachment($file, array $options = []) {
        PHPUnit::assertTrue(
            $this->hasAttachment($file, $options),
            'Did not find the expected attachment.'
        );

        return $this;
    }

    /**
     * @param string $data
     * @param string $name
     * @param array  $options
     *
     * @return $this
     */
    public function assertHasAttachedData($data, $name, array $options = []) {
        PHPUnit::assertTrue(
            $this->hasAttachedData($data, $name, $options),
            'Did not find the expected attachment.'
        );

        return $this;
    }

    /**
     * @param string      $path
     * @param null|string $name
     * @param array       $options
     *
     * @return $this
     */
    public function assertHasAttachmentFromStorage($path, $name = null, array $options = []) {
        PHPUnit::assertTrue(
            $this->hasAttachmentFromStorage($path, $name, $options),
            'Did not find the expected attachment.'
        );

        return $this;
    }

    /**
     * @param null|string $disk
     * @param string      $path
     * @param null|string $name
     * @param array       $options
     *
     * @return $this
     */
    public function assertHasAttachmentFromStorageDisk($disk, $path, $name = null, array $options = []) {
        PHPUnit::assertTrue(
            $this->hasAttachmentFromStorageDisk($disk, $path, $name, $options),
            'Did not find the expected attachment.'
        );

        return $this;
    }

    /**
     * Set the name of the mailer that should send the message.
     *
     * @param string $mailer
     *
     * @return $this
     */
    public function mailer($mailer) {
        $this->mailer = $mailer;

        return $this;
    }

    /**
     * Register a callback to be called with the Symfony message instance.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function withSymfonyMessage($callback) {
        $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to be called while building the view data.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function buildViewDataUsing(callable $callback) {
        static::$viewDataCallback = $callback;
    }

    /**
     * Dynamically bind parameters to the message.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws \BadMethodCallException
     *
     * @return $this
     */
    public function __call($method, $parameters) {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (str_starts_with($method, 'with')) {
            return $this->with(cstr::camel(substr($method, 4)), $parameters[0]);
        }

        static::throwBadMethodCallException($method);
    }
}
