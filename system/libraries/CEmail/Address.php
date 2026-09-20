<?php

use Symfony\Component\Mime\Address as SymfonyAddress;

/**
 * Normalisasi semua bentuk alamat yang dipakai app ke satu bentuk: `['email' => …, 'name' => …]`.
 * Bentuk yang diterima: string `a@b.c`, string `Nama <a@b.c>`, string berkoma/titik koma berisi beberapa alamat,
 * `['email','name']`, `['toEmail','toName']` (SendGrid), `['address','name']`, objek dengan properti
 * `email`/`name`, `Symfony\Component\Mime\Address`, dan array campuran dari semuanya.
 */
class CEmail_Address {
    /**
     * @param mixed $value
     *
     * @return array daftar `['email' => string, 'name' => null|string]`, alamat kosong dibuang
     */
    public static function normalize($value) {
        $result = [];
        foreach (static::items($value) as $item) {
            $entry = static::normalizeOne($item);
            if ($entry !== null) {
                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return SymfonyAddress[]
     */
    public static function toSymfony($value) {
        return array_map(function ($entry) {
            return new SymfonyAddress($entry['email'], (string) $entry['name']);
        }, static::normalize($value));
    }

    /**
     * @param mixed $value
     *
     * @return string[] alamat email saja
     */
    public static function emails($value) {
        return array_map(function ($entry) {
            return $entry['email'];
        }, static::normalize($value));
    }

    /**
     * Pecah nilai menjadi item tunggal: array direkursi, string berkoma/titik koma dipisah
     * (kecuali koma di dalam nama berkutip).
     *
     * @param mixed $value
     *
     * @return array
     */
    protected static function items($value) {
        if ($value === null || $value === '' || $value === false) {
            return [];
        }
        if (is_string($value)) {
            if (strpos($value, ',') === false && strpos($value, ';') === false) {
                return [trim($value)];
            }

            return array_map('trim', preg_split('/[,;](?=(?:[^"]*"[^"]*")*[^"]*$)/', $value));
        }
        if (is_array($value) && !static::isSingle($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                // bentuk ['a@b.c' => 'Nama']
                if (is_string($key) && strpos($key, '@') !== false && (is_string($item) || $item === null)) {
                    $items[] = ['email' => $key, 'name' => $item];
                } else {
                    $items = array_merge($items, static::items($item));
                }
            }

            return $items;
        }

        return [$value];
    }

    /**
     * @param array $value
     *
     * @return bool array asosiatif yang mewakili satu alamat
     */
    protected static function isSingle(array $value) {
        foreach (['email', 'toEmail', 'address'] as $key) {
            if (array_key_exists($key, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $item
     *
     * @return null|array
     */
    protected static function normalizeOne($item) {
        $email = null;
        $name = null;
        if ($item instanceof SymfonyAddress) {
            $email = $item->getAddress();
            $name = $item->getName();
        } elseif (is_array($item)) {
            $email = carr::get($item, 'email', carr::get($item, 'toEmail', carr::get($item, 'address')));
            $name = carr::get($item, 'name', carr::get($item, 'toName'));
        } elseif (is_object($item)) {
            $email = isset($item->email) ? $item->email : (isset($item->address) ? $item->address : null);
            $name = isset($item->name) ? $item->name : null;
        } elseif (is_string($item)) {
            $email = trim($item);
            if (preg_match('/^\s*(?:"?(?<name>[^"<]*?)"?\s*)?<(?<email>[^>]+)>\s*$/', $email, $matches)) {
                $email = trim($matches['email']);
                $name = trim($matches['name']);
            }
        }
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }
        $name = trim((string) $name);

        return ['email' => $email, 'name' => $name === '' ? null : $name];
    }
}
