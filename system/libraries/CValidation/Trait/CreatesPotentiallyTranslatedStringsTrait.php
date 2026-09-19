<?php

/**
 * Membuat `$fail()` yang mengembalikan string yang bisa diterjemahkan lewat
 * `->translate()`; pesannya baru ditulis ke `$this->messages` saat objek itu
 * dilepas. Pemakai wajib punya `$this->validator` dan `$this->messages`.
 */
trait CValidation_Trait_CreatesPotentiallyTranslatedStringsTrait {
    /**
     * Create a pending potentially translated string.
     *
     * @param string      $attribute
     * @param null|string $message
     *
     * @return \CTranslation_PotentiallyTranslatedString
     */
    protected function pendingPotentiallyTranslatedString($attribute, $message) {
        $destructor = $message === null
            ? fn ($message) => $this->messages[] = $message
            : fn ($message) => $this->messages[$attribute] = $message;

        return new class($message ?? $attribute, $this->validator->getTranslator(), $destructor) extends CTranslation_PotentiallyTranslatedString {
            /**
             * The callback to call when the object destructs.
             *
             * @var \Closure
             */
            protected $destructor;

            /**
             * Create a new pending potentially translated string.
             *
             * @param string                            $message
             * @param \CTranslation_TranslatorInterface $translator
             * @param \Closure                          $destructor
             */
            public function __construct($message, $translator, $destructor) {
                parent::__construct($message, $translator);

                $this->destructor = $destructor;
            }

            /**
             * Handle the object's destruction.
             *
             * @return void
             */
            public function __destruct() {
                ($this->destructor)($this->toString());
            }
        };
    }
}
