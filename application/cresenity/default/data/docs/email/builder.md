# Email Builder

`CEmail::builder()` menyusun email HTML yang kompatibel dengan Outlook, Gmail, dan klien lain dari pohon komponen (port MJML 4.x; kelas CSS memakai awalan `c-` bukan `mj-`). Anda menulis pohonnya dengan PHP, hasilnya HTML siap kirim.

```php
$builder = CEmail::builder()->createRuntimeBuilder();

$builder->addHead()->addTitle('Selamat datang');

$section = $builder->addBody()->setWidth('600px')->addSection();
$column = $section->addColumn();
$column->addText()->add('Halo, <b>Hery</b>!');
$column->addButton()->setHref('https://example.com')->add('Buka');

$html = $builder->render();
```

Demo yang merender hasilnya ke iframe: `demo/email/builder`.

---

### Struktur pohon

`addHead()` (opsional) dan `addBody()` adalah anak langsung builder. `addBody()` wajib; tanpanya `render()` melempar `CEmail_Builder_Exception`.

| Helper | Tag | Keterangan |
|---|---|---|
| `addSection()` | `c-section` | Baris selebar body. `setFullWidth('full-width')`, `setBackgroundColor()`, `setBackgroundUrl()` |
| `addWrapper()` | `c-wrapper` | Pembungkus beberapa section dengan satu latar/border |
| `addGroup()` | `c-group` | Kolom yang tidak bertumpuk di mobile |
| `addColumn()` | `c-column` | Kolom di dalam section/group; `setWidth('50%')` |
| `addText()` | `c-text` | Teks/HTML; isi lewat `add('...')` |
| `addImage()` | `c-image` | `setSrc()`, `setAlt()`, `setHref()` |
| `addButton()` | `c-button` | `setHref()`, `setBackgroundColor()` |
| `addDivider()` | `c-divider` | Garis pemisah |
| `addSpacer()` | `c-spacer` | Jarak vertikal; `setHeight('20px')` (bawaan 20px) |
| `addSocial()` / `addSocialElement()` | `c-social` / `c-social-element` | Ikon jejaring sosial; `setName('facebook')->setHref(...)` |
| `addRaw()` | `c-raw` | HTML mentah apa adanya; isi lewat `add('<p>..</p>')` |

Atribut diset dengan `set<NamaAtribut>()`: `setFontSize('14px')` menjadi `font-size`. Nilai atribut di-escape otomatis; isi teks lewat `add()` tidak (memang HTML).

---

### Head

Semua helper ini dipanggil pada `addHead()`.

| Helper | Fungsi |
|---|---|
| `addTitle($text)` | Isi `<title>` |
| `addPreview($text)` | Teks pratinjau (preheader) yang tampil di daftar inbox |
| `addFont($name, $href)` | Mendaftarkan font web; `<link>` hanya disisipkan bila font itu dipakai di email |
| `addBreakpoint($width)` | Lebar media query kolom bertumpuk (bawaan `480px`) |
| `addStyle($css, $inline = false)` | CSS tambahan di `<head>`; `$inline = true` untuk CSS yang di-inline |
| `addHeadAttributes()` | Default atribut, lihat di bawah |

#### Default atribut: `c-all` dan `c-class`

Setel sekali, bukan di tiap komponen:

```php
$defaults = $builder->addHead()->addHeadAttributes();

$defaults->addAll()->setFontFamily('Arial, sans-serif')->setColor('#333333');   // semua komponen
$defaults->addNode('c-text')->setFontSize('15px');                              // semua c-text
$defaults->addClass('lead')->setFontSize('18px')->setColor('#1568b0');         // kelas bernama

$column->addText()->useClass('lead')->add('Judul');
```

Urutan prioritas, yang belakangan menang: bawaan komponen, `addAll()`, default per tag, kelas lewat `useClass()` (spasi memisahkan beberapa nama), atribut pada node itu sendiri.

Atribut yang tidak ada di daftar atribut sebuah komponen **diabaikan**. Misalnya `addBody()->setColor()` tidak mewarisi ke teks. Pakai `addAll()` untuk itu, dan lihat validator di bawah.

---

### Opsi `render()`

```php
$html = $builder->render([
    'fonts' => [],                  // tanpa <link> font sama sekali
    'validationLevel' => 'strict',  // soft (bawaan), strict, atau skip
    'keepComments' => false,
]);
```

#### Font

Bawaan, email yang memakai `Ubuntu`, `Open Sans`, `Droid Sans`, `Lato`, atau `Roboto` (termasuk lewat default atribut) menyisipkan `<link>` Google Fonts. Untuk mematikannya di semua email app, atur di config app `email.php`:

```php
'builder' => [
    'fonts' => [],                  // atau daftar 'Nama' => 'https://.../css?family=...'
    'validation_level' => 'soft',
],
```

Opsi `fonts` pada `render()` mengalahkan config.

#### Validator atribut

Sebelum merender, atribut yang tidak dikenal sebuah komponen dilaporkan:

| Level | Perilaku |
|---|---|
| `soft` | Peringatan di log, sekali per pesan per proses. Hasil render tidak berubah |
| `strict` | Melempar `CEmail_Builder_Exception` |
| `skip` | Tidak diperiksa |

Contoh pesan: `atribut "color" tidak dikenal pada <c-body> dan diabaikan`.

---

### Render bersarang dan worker

Setiap `render()` memakai data globalnya sendiri, jadi merender email lain dari dalam sebuah komponen, atau merender banyak email berurutan dalam satu proses worker antrean, tidak saling menimpa. `CEmail::builder()->globalData()` mengembalikan data render yang sedang berjalan.

---

### Dari XML (CML)

`CEmail::builder()->toHtml($cml, $options)` menerima markup `<c-body>...</c-body>` dengan atribut dan opsi yang sama. Memakai tag di tabel di atas (`c-section`, `c-column`, ...), plus `c-head`, `c-attributes`, `c-all`, `c-class`, `c-title`, `c-preview`, `c-font`, `c-breakpoint`, `c-style`.
