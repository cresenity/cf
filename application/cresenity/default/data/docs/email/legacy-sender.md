# Legacy Sender

`CEmail::sender($config)->send($to, $subject, $body, $options)` dan driver `CEmail_Driver_*` adalah jalur kirim lama. Jalur ini tetap berjalan, tetapi ditandai `@deprecated`; kode baru memakai `CEmail::mailer()` dengan Mailable.

---

### Saklar `email.legacy_sender_via_mailer`

```php
// default/config/email.php
'legacy_sender_via_mailer' => true,
```

Bernilai `false` secara default: `CEmail::sender()` memakai driver lama seperti biasa. Bernilai `true`, pemanggilannya tidak berubah (`$to`, `$options`, exception yang bisa ditangkap `CEmail_Exception_*`), tetapi pengirimannya lewat `CEmail_Mailer` (Symfony Mailer). Hasilnya:

- Event `CEmail_Event_MessageSending`/`MessageSent` ikut terpancar.
- `CEmail::fake()` menangkap pengirimannya (lihat [Testing Email](/docs/page/email/testing)).
- `send()` mengembalikan `CEmail_SentMessage`, atau `false` bila event `MessageSending` memveto.

Mailer dibangun sekali per konfigurasi pengirim dan dipakai ulang.

---

### Memilih provider (dan kenapa `MAIL_MAILER` tidak berlaku)

`CEmail::sender()` memilih provider dengan urutan ini, dan **tidak pernah membaca `email.default` atau `MAIL_MAILER`**:

1. `driver` eksplisit pada config pengirim (`CEmail::sender(['driver' => 'brevo', ...])`).
2. Ditebak dari `smtp_host`: `smtp.sendgrid.net` → SendGrid, `smtp-relay.brevo.com` → Brevo, `smtp.mailgun.org` → Mailgun, dst; host lain → SMTP biasa.

Hanya `CEmail::mailer()` yang memakai `MAIL_MAILER`. Akibatnya app yang mengisi `MAIL_MAILER=brevo` tetapi masih punya `app.smtp_host = 'smtp.sendgrid.net'` (misalnya sisa salinan dari app lain) tetap mengirim lewat SendGrid. Cara memperbaikinya: isi `driver` secara eksplisit pada config pengirim, atau hapus `smtp_*` yang basi dari `default/config/app.php`.

Dua alat bantu mendeteksinya:

- Saat provider ditebak dari `smtp_host` dan `MAIL_MAILER` diisi eksplisit dengan transport berbeda, framework menulis satu peringatan log per proses. Provider yang dipakai tidak berubah.
- `phpcf email:check` (dari folder app) memeriksa konfigurasi app saat ini dan tidak mengubah apa pun. Temuan `warning` (konflik `MAIL_MAILER`, `smtp_password` kosong untuk provider API) membuat exit code 1; temuan `notice` (domain `smtp_from` berbeda dari domain app, jumlah berkas yang memanggil `CEmail::sender()`) hanya informasi. `--json` menghasilkan keluaran mesin.

---

### Pemetaan opsi lama

| Opsi `send()` | Pada pesan |
|---|---|
| `$to` | `to`; string, `'Nama <a@b.c>'`, `['toEmail' => , 'toName' => ]`, `['email' => , 'name' => ]`, objek, atau campuran |
| `$subject` | `subject` |
| `cc`, `bcc` | `cc`, `bcc`, bentuk alamat sama dengan `$to` |
| `reply_to` atau `replyTo` | `replyTo` |
| `returnPath` | `returnPath` |
| `priority` | `priority` |
| `headers` | header teks (`nama => nilai`) |
| `attachments` atau `attachment` | path, `['path'/'data', 'name', 'mime']`, atau `CEmail_Attachment` |
| `from`, `from_name` | pengirim; urutan fallback `from` → `smtp_from` → `app.email.from` → `app.smtp_from` |
| `type` = `plain...` | isi dikirim sebagai teks; selain itu HTML |

Konfigurasi pengirim (`driver`, `host`, `port`, `username`, `password`, `smtp_*`) dipetakan ke satu mailer oleh `CEmail_Config::toMailerConfig()`.

---

### Migrasi ke Mailer

| Lama | Baru |
|---|---|
| `CEmail::sender($config)->send($to, $subject, $html)` | `CEmail::mailer()->html($html, function ($message) use ($to, $subject) { $message->to($to)->subject($subject); })` |
| Sama, tapi berulang di banyak tempat | Satu kelas `CEmail_Mailable`, `CEmail::to($to)->send(new XEmail(...))` |
| `smtp_*` di config pengirim | `email.mailers.<nama>` lalu `CEmail::mailer('<nama>')` |
| Isi email dari `buildEmailBuilder()` per app | [Email Template](/docs/page/email/template) |

Mailable gaya baru dijelaskan di [Mailable](/docs/page/email/mailable).
