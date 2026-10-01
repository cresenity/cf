<?php

/**
 * Amplop sebuah Mailable: pengirim, penerima, subjek, tag, metadata, dan callback pesan Symfony.
 */
class CEmail_Mailable_Envelope {
    /**
     * @var null|CEmail_Mailable_Address
     */
    public $from;

    /**
     * @var CEmail_Mailable_Address[]
     */
    public $to = [];

    /**
     * @var CEmail_Mailable_Address[]
     */
    public $cc = [];

    /**
     * @var CEmail_Mailable_Address[]
     */
    public $bcc = [];

    /**
     * @var CEmail_Mailable_Address[]
     */
    public $replyTo = [];

    /**
     * @var null|string
     */
    public $subject;

    /**
     * @var string[]
     */
    public $tags = [];

    /**
     * @var array
     */
    public $metadata = [];

    /**
     * @var Closure[]
     */
    public $using = [];

    /**
     * @param array $attributes from, to, cc, bcc, replyTo, subject, tags, metadata, using
     */
    public function __construct(array $attributes = []) {
        if (isset($attributes['from'])) {
            $this->from($attributes['from']);
        }
        foreach (['to', 'cc', 'bcc', 'replyTo'] as $type) {
            if (isset($attributes[$type])) {
                $this->{$type}($attributes[$type]);
            }
        }
        if (isset($attributes['subject'])) {
            $this->subject($attributes['subject']);
        }
        foreach ((array) carr::get($attributes, 'tags', []) as $tag) {
            $this->tag($tag);
        }
        foreach ((array) carr::get($attributes, 'metadata', []) as $key => $value) {
            $this->metadata($key, $value);
        }
        foreach ((array) carr::get($attributes, 'using', []) as $callback) {
            $this->using($callback);
        }
    }

    /**
     * @param mixed       $address string, daftar alamat, objek dengan email/name, atau CEmail_Mailable_Address
     * @param null|string $name
     *
     * @return CEmail_Mailable_Address[]
     */
    protected static function addresses($address, $name = null) {
        if ($address instanceof CEmail_Mailable_Address) {
            return [$address];
        }
        if (is_string($address) && $name !== null) {
            $address = [['email' => $address, 'name' => $name]];
        }
        $result = [];
        foreach ((array) $address as $item) {
            if ($item instanceof CEmail_Mailable_Address) {
                $result[] = $item;

                continue;
            }
            foreach (CEmail_Address::normalize($item) as $entry) {
                $result[] = new CEmail_Mailable_Address($entry['email'], $entry['name']);
            }
        }

        return $result;
    }

    /**
     * @param mixed       $address
     * @param null|string $name
     *
     * @return $this
     */
    public function from($address, $name = null) {
        $addresses = static::addresses($address, $name);
        $this->from = count($addresses) > 0 ? $addresses[0] : null;

        return $this;
    }

    /**
     * @param mixed       $address
     * @param null|string $name
     *
     * @return $this
     */
    public function to($address, $name = null) {
        $this->to = array_merge($this->to, static::addresses($address, $name));

        return $this;
    }

    /**
     * @param mixed       $address
     * @param null|string $name
     *
     * @return $this
     */
    public function cc($address, $name = null) {
        $this->cc = array_merge($this->cc, static::addresses($address, $name));

        return $this;
    }

    /**
     * @param mixed       $address
     * @param null|string $name
     *
     * @return $this
     */
    public function bcc($address, $name = null) {
        $this->bcc = array_merge($this->bcc, static::addresses($address, $name));

        return $this;
    }

    /**
     * @param mixed       $address
     * @param null|string $name
     *
     * @return $this
     */
    public function replyTo($address, $name = null) {
        $this->replyTo = array_merge($this->replyTo, static::addresses($address, $name));

        return $this;
    }

    /**
     * @param string $subject
     *
     * @return $this
     */
    public function subject($subject) {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @param string $tag
     *
     * @return $this
     */
    public function tag($tag) {
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function metadata($key, $value) {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Callback yang menerima pesan Symfony sebelum dikirim.
     *
     * @param Closure $callback
     *
     * @return $this
     */
    public function using(Closure $callback) {
        $this->using[] = $callback;

        return $this;
    }

    /**
     * @param string      $address
     * @param null|string $name
     *
     * @return bool
     */
    public function isFrom($address, $name = null) {
        return $this->from !== null && static::matches($this->from, $address, $name);
    }

    /**
     * @param string      $address
     * @param null|string $name
     *
     * @return bool
     */
    public function hasTo($address, $name = null) {
        return $this->hasAddress($this->to, $address, $name);
    }

    /**
     * @param string      $address
     * @param null|string $name
     *
     * @return bool
     */
    public function hasCc($address, $name = null) {
        return $this->hasAddress($this->cc, $address, $name);
    }

    /**
     * @param string      $address
     * @param null|string $name
     *
     * @return bool
     */
    public function hasBcc($address, $name = null) {
        return $this->hasAddress($this->bcc, $address, $name);
    }

    /**
     * @param string      $address
     * @param null|string $name
     *
     * @return bool
     */
    public function hasReplyTo($address, $name = null) {
        return $this->hasAddress($this->replyTo, $address, $name);
    }

    /**
     * @param string $subject
     *
     * @return bool
     */
    public function hasSubject($subject) {
        return $this->subject === $subject;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    public function hasMetadata($key, $value) {
        return isset($this->metadata[$key]) && $this->metadata[$key] === $value;
    }

    /**
     * @param CEmail_Mailable_Address[] $list
     * @param string                    $address
     * @param null|string               $name
     *
     * @return bool
     */
    protected function hasAddress(array $list, $address, $name) {
        foreach ($list as $item) {
            if (static::matches($item, $address, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nama hanya dibandingkan bila diberikan.
     *
     * @param CEmail_Mailable_Address $actual
     * @param string                  $address
     * @param null|string             $name
     *
     * @return bool
     */
    protected static function matches(CEmail_Mailable_Address $actual, $address, $name) {
        return $actual->address === $address && ($name === null || $actual->name === $name);
    }
}
