# Email Template

`CEmail::template()` memberi kerangka email yang sama untuk seluruh app: header (logo atau nama app), area isi, dan footer. Anda hanya mengisi bagian isi; warna, font, lebar, dan footer diatur dari config. Ini menggantikan `buildEmailBuilder()` yang sebelumnya disalin ke tiap app.

```php
$template = CEmail::template()->title('Atur ulang sandi')->preview('Klik tombol untuk melanjutkan');

$column = $template->bodySection()->addColumn();
$column->addText()->add('Halo, kami menerima permintaan atur ulang sandi.');
$column->addButton()->setHref($url)->add('Atur Ulang Sandi');

$html = $template->render();
```

`bodySection()` mengembalikan section putih tempat konten ditambahkan dengan helper builder biasa (lihat [Email Builder](/docs/page/email/builder)). `builder()` mengembalikan `CEmail_Builder_RuntimeBuilder` penuh bila Anda perlu menambah section lain.

Demo: `demo/email/template`.

---

### Config `email.template`

```php
'template' => [
    'class' => null,                 // turunan CEmail_Builder_Template; kosong = DefaultTemplate
    'logo_url' => null,              // kosong = tampilkan nama app sebagai teks
    'app_name' => null,              // kosong = config app.name
    'primary_color' => '#1a347b',    // garis header dan latar footer
    'background_color' => '#c4c4c4',
    'text_color' => '#555555',       // warna teks bawaan c-text
    'font_family' => 'Arial, sans-serif',
    'width' => '650px',
    'footer_text' => null,           // HTML; {year} dan {app_name} diganti
    'show_header' => true,
    'show_footer' => true,
],
```

Config app yang mendefinisikan kunci `template` mengganti seluruh blok, jadi cukup isi kunci yang ingin Anda ubah; yang tidak diisi memakai bawaan di atas. Opsi yang sama bisa diberikan per pemakaian: `CEmail::template(['primary_color' => '#aa0000'])`.

---

### Subclass

Untuk logika yang tidak cukup dengan config, turunkan kelasnya dan arahkan `email.template.class` ke sana:

```php
class MyEmailTemplate extends CEmail_Builder_Template {
    public function logoUrl() {
        return c::url('media/img/logo.png');
    }

    public function footerText() {
        return 'PT Contoh &middot; Jakarta';
    }
}
```

Method yang bisa di-override: `logoUrl()`, `appName()`, `primaryColor()`, `backgroundColor()`, `textColor()`, `fontFamily()`, `width()`, `footerText()`, `showHeader()`, `showFooter()`. Untuk mengubah struktur, override `buildHeader()` atau `buildFooter()` (keduanya menerima node body), atau `build()`.

Kelas di `email.template.class` yang bukan turunan `CEmail_Builder_Template` melempar `CEmail_Builder_Exception`.

---

### Dari kelas notifikasi lama

`CNotification_MethodAbstract` punya helper `emailTemplate()` sehingga kelas notifikasi email yang sudah ada bisa berhenti menyalin builder tanpa migrasi penuh:

```php
$template = $this->emailTemplate(['logo_url' => $logoUrl]);
$template->bodySection()->addColumn()->addText()->add($message);
$html = $template->render();
```
