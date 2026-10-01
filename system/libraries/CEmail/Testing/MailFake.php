<?php

use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Pengganti CEmail_MailManager/CEmail_Mailer untuk test: mencatat Mailable yang dikirim atau diantre dan
 * pesan jalur lama (CEmail::sender() di mode adaptor, mailer->html()/raw()), tanpa mengirim apa pun.
 */
class CEmail_Testing_MailFake implements CEmail_Contract_FactoryInterface, CEmail_Contract_MailerInterface, CEmail_Contract_MailQueueInterface {
    /**
     * @var null|string
     */
    protected $currentMailer;

    /**
     * @var CEmail_Contract_MailableInterface[]
     */
    protected $mailables = [];

    /**
     * @var CEmail_Contract_MailableInterface[]
     */
    protected $queuedMailables = [];

    /**
     * @var CEmail_Mailable_LegacyMailable[]
     */
    protected $legacyMailables = [];

    /**
     * @param null|string $name
     *
     * @return $this
     */
    public function mailer($name = null) {
        $this->currentMailer = $name;

        return $this;
    }

    /**
     * @param null|string $name
     *
     * @return $this
     */
    public function driver($name = null) {
        return $this->mailer($name);
    }

    /**
     * Dipanggil CEmail_Sender_MailerDriver; semua pengiriman jalur lama berakhir di fake ini.
     *
     * @param array $config
     *
     * @return $this
     */
    public function build($config = []) {
        return $this;
    }

    /**
     * @param mixed $users
     *
     * @return CEmail_PendingMail
     */
    public function to($users) {
        return (new CEmail_PendingMail($this))->to($users);
    }

    /**
     * @param mixed $users
     *
     * @return CEmail_PendingMail
     */
    public function cc($users) {
        return (new CEmail_PendingMail($this))->cc($users);
    }

    /**
     * @param mixed $users
     *
     * @return CEmail_PendingMail
     */
    public function bcc($users) {
        return (new CEmail_PendingMail($this))->bcc($users);
    }

    /**
     * @param mixed         $view
     * @param array         $data
     * @param null|callable $callback
     *
     * @return null|bool
     */
    public function send($view, array $data = [], $callback = null) {
        if (!$view instanceof CEmail_Contract_MailableInterface) {
            return $this->recordLegacy(is_array($view) ? $view : [$view], $callback);
        }

        $view->mailer($this->currentMailer);

        if ($view instanceof CQueue_ShouldQueueInterface) {
            return $this->queue($view, $data);
        }

        $this->currentMailer = null;
        $this->mailables[] = $view;

        return null;
    }

    /**
     * @param string        $html
     * @param null|callable $callback
     *
     * @return bool
     */
    public function html($html, $callback = null) {
        return $this->recordLegacy(['html' => (string) $html], $callback);
    }

    /**
     * @param string        $text
     * @param null|callable $callback
     *
     * @return bool
     */
    public function raw($text, $callback = null) {
        return $this->recordLegacy(['raw' => (string) $text], $callback);
    }

    /**
     * @param string        $view
     * @param array         $data
     * @param null|callable $callback
     *
     * @return bool
     */
    public function plain($view, array $data = [], $callback = null) {
        return $this->recordLegacy(['plain' => $view], $callback);
    }

    /**
     * @param array         $view
     * @param null|callable $callback
     *
     * @return bool
     */
    protected function recordLegacy(array $view, $callback) {
        $message = new CEmail_Message();
        if ($callback !== null) {
            $callback($message);
        }

        $type = 'view';
        $body = null;
        foreach (['html', 'raw', 'plain'] as $candidate) {
            if (isset($view[$candidate])) {
                $type = $candidate;
                $body = $view[$candidate];

                break;
            }
        }
        if ($type === 'view') {
            $body = carr::first($view);
        }
        $this->legacyMailables[] = CEmail_Mailable_LegacyMailable::fromMessage($message, $body, $type);
        $this->currentMailer = null;

        return true;
    }

    /**
     * @param mixed       $view
     * @param null|string $queue
     *
     * @return null|mixed
     */
    public function queue($view, $queue = null) {
        if (!$view instanceof CEmail_Contract_MailableInterface) {
            return null;
        }

        $view->mailer($this->currentMailer);
        $this->currentMailer = null;
        $this->queuedMailables[] = $view;

        return null;
    }

    /**
     * @param mixed       $delay
     * @param mixed       $view
     * @param null|string $queue
     *
     * @return null|mixed
     */
    public function later($delay, $view, $queue = null) {
        return $this->queue($view, $queue);
    }

    // ---- query ----

    /**
     * @param string        $mailable kelas Mailable
     * @param null|callable $callback menerima Mailable, true berarti dihitung
     *
     * @return CCollection
     */
    public function sent($mailable, $callback = null) {
        return $this->filter($this->mailables, $mailable, $callback);
    }

    /**
     * @param string        $mailable
     * @param null|callable $callback
     *
     * @return CCollection
     */
    public function queued($mailable, $callback = null) {
        return $this->filter($this->queuedMailables, $mailable, $callback);
    }

    /**
     * @param null|callable $callback menerima CEmail_Mailable_LegacyMailable
     *
     * @return CCollection
     */
    public function legacySent($callback = null) {
        return $this->filter($this->legacyMailables, CEmail_Mailable_LegacyMailable::class, $callback);
    }

    /**
     * @param string $mailable
     *
     * @return bool
     */
    public function hasSent($mailable) {
        return $this->sent($mailable)->count() > 0;
    }

    /**
     * @param string $mailable
     *
     * @return bool
     */
    public function hasQueued($mailable) {
        return $this->queued($mailable)->count() > 0;
    }

    /**
     * @param array         $list
     * @param string        $class
     * @param null|callable $callback
     *
     * @return CCollection
     */
    protected function filter(array $list, $class, $callback = null) {
        return c::collect($list)->filter(function ($mailable) use ($class, $callback) {
            return $mailable instanceof $class && ($callback === null || $callback($mailable));
        })->values();
    }

    /**
     * @param string|Closure $mailable
     * @param null|callable  $callback
     *
     * @return array [string $class, null|callable|int $callback]
     */
    protected function prepare($mailable, $callback) {
        if ($mailable instanceof Closure) {
            $parameters = (new ReflectionFunction($mailable))->getParameters();
            $type = isset($parameters[0]) ? $parameters[0]->getType() : null;

            return [$type instanceof ReflectionNamedType ? $type->getName() : CEmail_Mailable::class, $mailable];
        }

        return [$mailable, $callback];
    }

    // ---- assertion ----

    /**
     * @param string|Closure    $mailable kelas, atau Closure dengan parameter bertipe Mailable
     * @param null|callable|int $callback filter, atau jumlah yang diharapkan
     *
     * @return void
     */
    public function assertSent($mailable, $callback = null) {
        list($mailable, $callback) = $this->prepare($mailable, $callback);

        if (is_numeric($callback)) {
            $this->assertSentTimes($mailable, (int) $callback);

            return;
        }

        $message = "The expected [{$mailable}] mailable was not sent.";
        if (count($this->queuedMailables) > 0) {
            $message .= ' Did you mean to use assertQueued() instead?';
        }

        PHPUnit::assertTrue($this->sent($mailable, $callback)->count() > 0, $message);
    }

    /**
     * @param string $mailable
     * @param int    $times
     *
     * @return void
     */
    protected function assertSentTimes($mailable, $times = 1) {
        $count = $this->sent($mailable)->count();

        PHPUnit::assertSame($times, $count, "The expected [{$mailable}] mailable was sent {$count} times instead of {$times} times.");
    }

    /**
     * @param string|Closure $mailable
     * @param null|callable  $callback
     *
     * @return void
     */
    public function assertNotSent($mailable, $callback = null) {
        list($mailable, $callback) = $this->prepare($mailable, $callback);

        PHPUnit::assertCount(
            0,
            $this->sent($mailable, $callback),
            "The unexpected [{$mailable}] mailable was sent."
        );
    }

    /**
     * @return void
     */
    public function assertNothingSent() {
        $names = implode(', ', array_map('get_class', $this->mailables));

        PHPUnit::assertEmpty($this->mailables, 'The following mailables were sent unexpectedly: ' . $names);
    }

    /**
     * @param string|Closure    $mailable
     * @param null|callable|int $callback
     *
     * @return void
     */
    public function assertQueued($mailable, $callback = null) {
        list($mailable, $callback) = $this->prepare($mailable, $callback);

        if (is_numeric($callback)) {
            $this->assertQueuedTimes($mailable, (int) $callback);

            return;
        }

        $message = "The expected [{$mailable}] mailable was not queued.";
        if (count($this->mailables) > 0) {
            $message .= ' Did you mean to use assertSent() instead?';
        }

        PHPUnit::assertTrue($this->queued($mailable, $callback)->count() > 0, $message);
    }

    /**
     * @param string $mailable
     * @param int    $times
     *
     * @return void
     */
    protected function assertQueuedTimes($mailable, $times = 1) {
        $count = $this->queued($mailable)->count();

        PHPUnit::assertSame($times, $count, "The expected [{$mailable}] mailable was queued {$count} times instead of {$times} times.");
    }

    /**
     * @param string|Closure $mailable
     * @param null|callable  $callback
     *
     * @return void
     */
    public function assertNotQueued($mailable, $callback = null) {
        list($mailable, $callback) = $this->prepare($mailable, $callback);

        PHPUnit::assertCount(
            0,
            $this->queued($mailable, $callback),
            "The unexpected [{$mailable}] mailable was queued."
        );
    }

    /**
     * @return void
     */
    public function assertNothingQueued() {
        $names = implode(', ', array_map('get_class', $this->queuedMailables));

        PHPUnit::assertEmpty($this->queuedMailables, 'The following mailables were queued unexpectedly: ' . $names);
    }

    /**
     * @param int $count
     *
     * @return void
     */
    public function assertSentCount($count) {
        $actual = count($this->mailables);

        PHPUnit::assertSame($count, $actual, "The total number of mailables sent was {$actual} instead of {$count}.");
    }

    /**
     * @param int $count
     *
     * @return void
     */
    public function assertQueuedCount($count) {
        $actual = count($this->queuedMailables);

        PHPUnit::assertSame($count, $actual, "The total number of mailables queued was {$actual} instead of {$count}.");
    }

    /**
     * Terkirim, diantre, dan jalur lama dihitung bersama.
     *
     * @param int $count
     *
     * @return void
     */
    public function assertOutgoingCount($count) {
        $actual = count($this->mailables) + count($this->queuedMailables) + count($this->legacyMailables);

        PHPUnit::assertSame($count, $actual, "The total number of outgoing mailables was {$actual} instead of {$count}.");
    }

    /**
     * @return void
     */
    public function assertNothingOutgoing() {
        $this->assertOutgoingCount(0);
    }

    /**
     * Pesan jalur lama ditangkap, mis. CEmail::sender()->send() saat email.legacy_sender_via_mailer aktif.
     *
     * @param null|callable|int $callback menerima CEmail_Mailable_LegacyMailable, atau jumlah yang diharapkan
     *
     * @return void
     */
    public function assertLegacySent($callback = null) {
        if (is_numeric($callback)) {
            $count = $this->legacySent()->count();
            PHPUnit::assertSame((int) $callback, $count, "The legacy message was sent {$count} times instead of {$callback} times.");

            return;
        }

        PHPUnit::assertTrue(
            $this->legacySent($callback)->count() > 0,
            'The expected legacy message was not sent.'
            . (CEmail_Sender::viaMailer() ? '' : ' CEmail::sender() is only captured when email.legacy_sender_via_mailer is true.')
        );
    }
}
