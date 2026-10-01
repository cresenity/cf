# Testing Email

`CEmail::fake()` mengganti pengirim email dengan pencatat, jadi test bisa memeriksa email yang akan dikirim tanpa mengirim apa pun.

```php
public function testWelcomeEmailIsSent() {
    CEmail::fake();

    CEmail::to('user@example.com')->send(new WelcomeEmail($user));

    CEmail::assertSent(WelcomeEmail::class);
    CEmail::assertSent(function (WelcomeEmail $mailable) {
        return $mailable->hasTo('user@example.com') && $mailable->hasSubject('Selamat datang');
    });
    CEmail::assertNotSent(OtherEmail::class);
}
```

Fake dibuang otomatis oleh `CTesting_TestCase::tearDown()`. Di luar `CTesting_TestCase`, panggil `CEmail::forgetFake()` sendiri.

---

### Assertion

| Method | Lulus bila |
|---|---|
| `assertSent($class, $callbackOrCount = null)` | Mailable terkirim; `$class` boleh Closure dengan parameter bertipe Mailable; angka berarti jumlah persis |
| `assertNotSent($class, $callback = null)` | Tidak ada yang cocok terkirim |
| `assertNothingSent()` | Tidak ada Mailable terkirim |
| `assertQueued(...)`, `assertNotQueued(...)`, `assertNothingQueued()` | Padanannya untuk yang diantre |
| `assertSentCount($n)`, `assertQueuedCount($n)` | Jumlah total |
| `assertOutgoingCount($n)`, `assertNothingOutgoing()` | Terkirim, diantre, dan jalur lama dihitung bersama |
| `assertLegacySent($callbackOrCount = null)` | Pesan jalur lama tertangkap (lihat bawah) |

Pemanggilan assertion tanpa `CEmail::fake()` lebih dulu melempar `LogicException`.

Closure menerima Mailable-nya, jadi semua `has*()` dan `assert*()` Mailable bisa dipakai (lihat [Mailable](/docs/page/email/mailable)). `$fake->sent($class, $callback)` dan `$fake->queued($class, $callback)` mengembalikan koleksi Mailable untuk pemeriksaan lain.

### Yang dicatat sebagai diantre

Mailable yang mengimplementasikan `CQueue_ShouldQueueInterface`, atau dikirim lewat `->queue()` / `->later()`, dihitung sebagai diantre, bukan terkirim. Nama mailer yang dipilih (`CEmail::mailer('brevo')`) tercatat pada propertinya, `$mailable->mailer`.

---

### Jalur lama: `CEmail::sender()`

Pesan dari `CEmail::sender()->send(...)` hanya tertangkap bila `email.legacy_sender_via_mailer` bernilai `true`; di mode lama (driver `CEmail_Driver_*`) fake tidak ikut campur. Nyalakan saklar itu di config environment test app. Pesan yang tertangkap berbentuk `CEmail_Mailable_LegacyMailable`:

```php
CEmail::assertLegacySent(function (CEmail_Mailable_LegacyMailable $mail) {
    return $mail->hasTo('user@example.com') && $mail->hasSubject('Verifikasi');
});
```

Selain `hasTo()`, `hasCc()`, `hasBcc()`, `hasFrom()`, dan `hasSubject()`, tersedia `getBody()`, `getBodyType()` (`html`, `raw`, `plain`, atau `view`), `hasAttachments()`, dan `getMessage()` untuk pesan Symfony-nya. `CEmail::sender()->send()` mengembalikan `true` pada fake.
