<?php
use PHPUnit\Framework\TestCase;

class UjiEmail_Attachable implements CEmail_Contract_AttachableInterface {
    public function toMailAttachment() {
        return CEmail_Attachment::fromData(function () {
            return 'dari attachable';
        }, 'attachable.txt')->withMime('text/plain');
    }
}

/**
 * CEmail_Attachment::fromLegacy(): semua bentuk lampiran app menjadi CEmail_Attachment, dan
 * CEmail_Message::attach() menerima objek lampiran seperti CEmail_Mailable.
 */
class EmailAttachmentTest extends TestCase {
    /** @var string */
    protected $file;

    protected function setUp(): void {
        $this->file = tempnam(sys_get_temp_dir(), 'uji-lampiran') . '.txt';
        file_put_contents($this->file, 'isi berkas');
    }

    protected function tearDown(): void {
        @unlink($this->file);
    }

    /**
     * @return array [nama, mime, isi] tiap lampiran di email Symfony
     */
    protected function describe(Symfony\Component\Mime\Email $email) {
        return array_map(function (Symfony\Component\Mime\Part\DataPart $part) {
            $headers = $part->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename') ?: $headers->getHeaderParameter('Content-Type', 'name');

            return [$filename, $part->getMediaType() . '/' . $part->getMediaSubtype(), $part->getBody()];
        }, $email->getAttachments());
    }

    public function testEveryLegacyShapeBecomesAnAttachment() {
        $attachments = CEmail_Attachment::fromLegacy([
            $this->file,
            ['path' => $this->file, 'filename' => 'laporan.pdf', 'type' => 'application/pdf'],
            ['path' => $this->file, 'name' => 'nama.txt'],
            ['data' => 'data mentah', 'name' => 'catatan.csv', 'mime' => 'text/csv'],
            ['data' => function () {
                return 'data closure';
            }, 'filename' => 'closure.txt'],
            CEmail_Attachment::fromPath($this->file)->as('alias.txt'),
            new UjiEmail_Attachable(),
            '',
            [$this->file, ['data' => 'nested', 'name' => 'nested.txt']],
        ]);

        $this->assertCount(9, $attachments);
        $this->assertContainsOnlyInstancesOf(CEmail_Attachment::class, $attachments);
        $this->assertSame([], CEmail_Attachment::fromLegacy(null));
        $this->assertSame([], CEmail_Attachment::fromLegacy([]));
        $this->assertCount(1, CEmail_Attachment::fromLegacy($this->file), 'satu path string dibungkus');

        $transport = new CEmail_Transport_ArrayTransport();
        (new CEmail_Mailer('array', $transport))->html('<p>x</p>', function (CEmail_Message $message) use ($attachments) {
            $message->from('kirim@x.test')->to('a@x.test')->subject('Lampiran');
            foreach ($attachments as $attachment) {
                $message->attach($attachment);
            }
        });
        $described = $this->describe($transport->messages()->last()->getOriginalMessage());

        $this->assertSame([basename($this->file), 'text/plain', 'isi berkas'], $described[0], 'path string: nama dari basename, mime dideteksi');
        $this->assertSame(['laporan.pdf', 'application/pdf', 'isi berkas'], $described[1]);
        $this->assertSame(['nama.txt', 'text/plain', 'isi berkas'], $described[2]);
        $this->assertSame(['catatan.csv', 'text/csv', 'data mentah'], $described[3]);
        $this->assertSame(['closure.txt', 'application/octet-stream', 'data closure'], $described[4], 'tanpa mime: octet-stream');
        $this->assertSame(['alias.txt', 'text/plain', 'isi berkas'], $described[5]);
        $this->assertSame(['attachable.txt', 'text/plain', 'dari attachable'], $described[6]);
        $this->assertSame([basename($this->file), 'text/plain', 'isi berkas'], $described[7], 'array bersarang diratakan');
        $this->assertSame(['nested.txt', 'application/octet-stream', 'nested'], $described[8]);
    }

    public function testStorageDiskShapeReadsFromTheDisk() {
        $disk = 'uji_lampiran_' . uniqid();
        $root = sys_get_temp_dir() . '/' . $disk;
        CConfig::repository()->set('storage.disks.' . $disk, ['driver' => 'local', 'root' => $root]);
        CStorage::instance()->disk($disk)->put('folder/dari-disk.txt', 'isi dari disk');
        try {
            $attachments = CEmail_Attachment::fromLegacy([['path' => 'folder/dari-disk.txt', 'disk' => $disk], ['path' => 'folder/dari-disk.txt', 'disk' => $disk, 'filename' => 'ganti.txt', 'type' => 'text/csv']]);
            $transport = new CEmail_Transport_ArrayTransport();
            (new CEmail_Mailer('array', $transport))->html('<p>x</p>', function (CEmail_Message $message) use ($attachments) {
                $message->from('kirim@x.test')->to('a@x.test')->attach($attachments[0])->attach($attachments[1]);
            });
            $described = $this->describe($transport->messages()->last()->getOriginalMessage());
            $this->assertSame(['dari-disk.txt', 'text/plain', 'isi dari disk'], $described[0]);
            $this->assertSame(['ganti.txt', 'text/csv', 'isi dari disk'], $described[1], 'filename/type eksplisit menimpa tebakan disk');
        } finally {
            CStorage::instance()->disk($disk)->deleteDirectory('folder');
            @rmdir($root);
            CConfig::repository()->set('storage.disks.' . $disk, null);
        }
    }

    public function testMessageAttachStillAcceptsAPlainPath() {
        $transport = new CEmail_Transport_ArrayTransport();
        (new CEmail_Mailer('array', $transport))->html('<p>x</p>', function (CEmail_Message $message) {
            $message->from('kirim@x.test')->to('a@x.test')->attach($this->file, ['as' => 'polos.txt', 'mime' => 'text/plain'])->attachData('mentah', 'mentah.bin');
        });
        $described = $this->describe($transport->messages()->last()->getOriginalMessage());

        $this->assertSame(['polos.txt', 'text/plain', 'isi berkas'], $described[0]);
        $this->assertSame('mentah.bin', $described[1][0]);
    }
}
