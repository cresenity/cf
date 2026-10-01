# Mailable

`CEmail_Mailable` mendukung dua gaya. Gaya lama memakai `build()`; gaya baru memisahkan amplop, isi, header, dan lampiran ke method terpisah (`envelope()`, `content()`, `headers()`, `attachments()`). Keduanya boleh dipakai bersamaan, dan `build()` tidak lagi wajib ada.

```php
class WelcomeEmail extends CEmail_Mailable {
    protected $user;

    public function __construct($user) {
        $this->user = $user;
    }

    public function envelope() {
        return (new CEmail_Mailable_Envelope())
            ->from('noreply@example.com', 'Example')
            ->replyTo('support@example.com')
            ->subject('Selamat datang')
            ->tag('welcome')
            ->metadata('user_id', $this->user->getKey());
    }

    public function content() {
        $template = CEmail::template()->title('Selamat datang');
        $template->bodySection()->addColumn()->addText()->add('Halo, ' . c::e($this->user->name) . '!');

        return (new CEmail_Mailable_Content())->template($template);
    }

    public function headers() {
        return new CEmail_Mailable_Headers('welcome-' . $this->user->getKey() . '@example.com');
    }

    public function attachments() {
        return [CEmail_Attachment::fromPath('/path/ke/panduan.pdf')->as('panduan.pdf')];
    }
}

CEmail::to($user->email)->send(new WelcomeEmail($user));
```

---

### Envelope

`CEmail_Mailable_Envelope` menampung `from`, `to`, `cc`, `bcc`, `replyTo`, `subject`, `tag`, `metadata`, dan `using()`. Alamat boleh berupa string, `['email' => ..., 'name' => ...]`, daftar alamat, objek dengan properti `email`/`name`, atau `CEmail_Mailable_Address`. Bisa juga lewat konstruktor: `new CEmail_Mailable_Envelope(['to' => 'a@x.test', 'subject' => 'Judul'])`.

`using(function ($message) { ... })` menerima pesan Symfony (`Symfony\Component\Mime\Email`) tepat sebelum dikirim.

### Content

`CEmail_Mailable_Content`:

| Method | Fungsi |
|---|---|
| `view($name)` / `html($name)` / `text($name)` / `markdown($name)` | Nama view |
| `htmlString($html)` | HTML jadi, dipakai apa adanya |
| `with($key, $value)` | Data untuk view |
| `builder($runtimeBuilder, $renderOptions = [])` | HTML dari [Email Builder](/docs/page/email/builder) |
| `template($template, $renderOptions = [])` | HTML dari [Email Template](/docs/page/email/template) |

### Headers

`CEmail_Mailable_Headers($messageId, $references, $text)` mengatur `Message-ID`, `References` (id boleh dengan atau tanpa `<>`), dan header teks bebas, misalnya `->text('X-Campaign', 'welcome')`.

### Lampiran

`attachments()` mengembalikan daftar `CEmail_Attachment`, objek `CEmail_Contract_AttachableInterface`, atau path. `attachMany($files)` melampirkan banyak sekaligus: `['/a.txt', '/b.txt' => ['as' => 'b2.txt']]`.

Method bernama `envelope`, `content`, `headers`, atau `attachments` yang mengembalikan tipe lain (bukan kelas di atas) diabaikan, jadi Mailable lama yang kebetulan memakai nama itu tidak terpengaruh.

---

### Assertion

Berguna di test; amplop dan isi dari method baru ikut diperiksa tanpa mengirim:

```php
$mailable->assertFrom('noreply@example.com')
    ->assertHasTo('user@example.com')
    ->assertHasCc(...)->assertHasBcc(...)->assertHasReplyTo(...)
    ->assertHasSubject('Selamat datang')
    ->assertHasTag('welcome')
    ->assertHasMetadata('user_id', 7)
    ->assertHasAttachment('/path/ke/panduan.pdf')
    ->assertHasAttachedData($data, 'nama.txt')
    ->assertHasAttachmentFromStorage('dir/f.txt')
    ->assertHasAttachmentFromStorageDisk('s3', 'dir/f.txt');
```

Padanannya yang mengembalikan boolean: `hasFrom`, `hasTo`, `hasCc`, `hasBcc`, `hasReplyTo`, `hasSubject`, `hasTag`, `hasMetadata`, `hasAttachment`, `hasAttachedData`, `hasAttachmentFromStorage`, `hasAttachmentFromStorageDisk`.

---

### `CEmail::to()`, `cc()`, `bcc()`

```php
CEmail::to($users)->cc($admin)->send(new WelcomeEmail($user));
CEmail::to($user)->queue(new WelcomeEmail($user));
```

Sama dengan `CEmail::mailer()->to(...)` pada mailer bawaan.

### Pengalihan di lingkungan non-produksi

Untuk memastikan email dev dan staging tidak sampai ke pelanggan, isi di `.env` app non-produksi:

```
MAIL_NON_PRODUCTION_TO_ADDRESS=dev@example.com
MAIL_NON_PRODUCTION_TO_NAME=Dev
```

Selama CF tidak berjalan di produksi (`IN_PRODUCTION` tidak didefinisikan dan `ENVIRONMENT` bukan `production`), semua email hanya dikirim ke alamat itu dan cc/bcc dibuang. Kosong berarti tidak ada pengalihan. Jangan isi di server produksi. Kunci `email.to` yang sudah ada tetap berlaku tanpa memeriksa lingkungan dan menang atas ini.
